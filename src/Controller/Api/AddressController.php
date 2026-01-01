<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Address;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/addresses')]
#[OA\Tag(name: 'Addresses')]
#[IsGranted('ROLE_USER')]
final class AddressController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'api_addresses_list', methods: ['GET'])]
    #[OA\Get(summary: 'Lister les adresses client', responses: [new OA\Response(response: 200, description: 'Adresses')])]
    public function index(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = array_map(fn (Address $address) => $this->serializeAddress($address), $user->getAddresses()->toArray());

        return $this->json($payload);
    }

    #[Route('', name: 'api_addresses_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Créer une adresse',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['addressLine1', 'city', 'postalCode', 'country'],
                properties: [
                    new OA\Property(property: 'label', type: 'string', nullable: true),
                    new OA\Property(property: 'addressLine1', type: 'string'),
                    new OA\Property(property: 'addressLine2', type: 'string', nullable: true),
                    new OA\Property(property: 'city', type: 'string'),
                    new OA\Property(property: 'postalCode', type: 'string'),
                    new OA\Property(property: 'state', type: 'string', nullable: true),
                    new OA\Property(property: 'country', type: 'string'),
                    new OA\Property(property: 'isDefault', type: 'boolean'),
                    new OA\Property(property: 'isShipping', type: 'boolean'),
                    new OA\Property(property: 'isBilling', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Adresse créée'),
            new OA\Response(response: 400, description: 'Données invalides'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent() ?: '[]', true) ?? [];

        $addressLine1 = trim((string) ($payload['addressLine1'] ?? ''));
        $city = trim((string) ($payload['city'] ?? ''));
        $postalCode = trim((string) ($payload['postalCode'] ?? ''));
        $country = trim((string) ($payload['country'] ?? ''));

        if ('' === $addressLine1 || '' === $city || '' === $postalCode || '' === $country) {
            return $this->json(['message' => 'Champs obligatoires manquants.'], Response::HTTP_BAD_REQUEST);
        }

        $address = (new Address())
            ->setOwner($user)
            ->setLabel($payload['label'] ?? null)
            ->setAddressLine1($addressLine1)
            ->setAddressLine2($payload['addressLine2'] ?? null)
            ->setCity($city)
            ->setPostalCode($postalCode)
            ->setState($payload['state'] ?? null)
            ->setCountry($country)
            ->setIsDefault((bool) ($payload['isDefault'] ?? false))
            ->setIsShipping((bool) ($payload['isShipping'] ?? true))
            ->setIsBilling((bool) ($payload['isBilling'] ?? true));

        if ($address->isDefault()) {
            $this->unsetDefaultAddress($user);
        }

        $this->entityManager->persist($address);
        $this->entityManager->flush();

        return $this->json($this->serializeAddress($address), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_addresses_update', methods: ['PUT', 'PATCH'])]
    #[OA\Patch(
        summary: 'Mettre à jour une adresse',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'label', type: 'string', nullable: true),
                    new OA\Property(property: 'addressLine1', type: 'string'),
                    new OA\Property(property: 'addressLine2', type: 'string', nullable: true),
                    new OA\Property(property: 'city', type: 'string'),
                    new OA\Property(property: 'postalCode', type: 'string'),
                    new OA\Property(property: 'state', type: 'string', nullable: true),
                    new OA\Property(property: 'country', type: 'string'),
                    new OA\Property(property: 'isDefault', type: 'boolean'),
                    new OA\Property(property: 'isShipping', type: 'boolean'),
                    new OA\Property(property: 'isBilling', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Adresse mise à jour'),
            new OA\Response(response: 404, description: 'Adresse introuvable'),
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $address = $this->entityManager->getRepository(Address::class)->find($id);
        if (!$address instanceof Address || $address->getOwner()?->getId() !== $user->getId()) {
            return $this->json(['message' => 'Adresse introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent() ?: '[]', true) ?? [];

        if (array_key_exists('label', $payload)) {
            $address->setLabel($payload['label']);
        }
        if (array_key_exists('addressLine1', $payload)) {
            $address->setAddressLine1((string) $payload['addressLine1']);
        }
        if (array_key_exists('addressLine2', $payload)) {
            $address->setAddressLine2($payload['addressLine2']);
        }
        if (array_key_exists('city', $payload)) {
            $address->setCity((string) $payload['city']);
        }
        if (array_key_exists('postalCode', $payload)) {
            $address->setPostalCode((string) $payload['postalCode']);
        }
        if (array_key_exists('state', $payload)) {
            $address->setState($payload['state']);
        }
        if (array_key_exists('country', $payload)) {
            $address->setCountry((string) $payload['country']);
        }
        if (array_key_exists('isDefault', $payload)) {
            $address->setIsDefault((bool) $payload['isDefault']);
        }
        if (array_key_exists('isShipping', $payload)) {
            $address->setIsShipping((bool) $payload['isShipping']);
        }
        if (array_key_exists('isBilling', $payload)) {
            $address->setIsBilling((bool) $payload['isBilling']);
        }

        if ($address->isDefault()) {
            $this->unsetDefaultAddress($user, $address->getId());
        }

        $this->entityManager->flush();

        return $this->json($this->serializeAddress($address));
    }

    #[Route('/{id}', name: 'api_addresses_delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Supprimer une adresse',
        responses: [
            new OA\Response(response: 204, description: 'Adresse supprimée'),
            new OA\Response(response: 404, description: 'Adresse introuvable'),
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $address = $this->entityManager->getRepository(Address::class)->find($id);
        if (!$address instanceof Address || $address->getOwner()?->getId() !== $user->getId()) {
            return $this->json(['message' => 'Adresse introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($address);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function serializeAddress(Address $address): array
    {
        return [
            'id' => $address->getId(),
            'label' => $address->getLabel(),
            'addressLine1' => $address->getAddressLine1(),
            'addressLine2' => $address->getAddressLine2(),
            'city' => $address->getCity(),
            'postalCode' => $address->getPostalCode(),
            'state' => $address->getState(),
            'country' => $address->getCountry(),
            'isDefault' => $address->isDefault(),
            'isShipping' => $address->isShipping(),
            'isBilling' => $address->isBilling(),
        ];
    }

    private function unsetDefaultAddress(User $user, ?int $keepId = null): void
    {
        foreach ($user->getAddresses() as $address) {
            if (null !== $keepId && $address->getId() === $keepId) {
                continue;
            }
            if ($address->isDefault()) {
                $address->setIsDefault(false);
            }
        }
    }
}
