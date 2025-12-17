<?php

namespace App\Service;

use App\Entity\CustomerOrder;
use App\Entity\OrderDocument;
use App\Enum\DocumentType;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

final class OrderDocumentGenerator
{
    public function __construct(
        private readonly Environment $twig,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly ParameterBagInterface $params,
        private readonly RequestStack $requestStack,
        private readonly ?Options $dompdfOptions = null,
    ) {
    }

    public function generate(CustomerOrder $order, DocumentType $type, string $baseUrl = ''): OrderDocument
    {
        $html = $this->twig->render('documents/order_document.html.twig', [
            'order' => $order,
            'type' => $type,
            'base_url' => rtrim($this->resolveBaseUrl($baseUrl), '/'),
        ]);

        $dompdf = new Dompdf($this->dompdfOptions ?? new Options());
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $contents = $dompdf->output();

        $filename = sprintf(
            'order-%s-%s-%s.pdf',
            $order->getReference(),
            $type->value,
            uniqid()
        );

        $relativeDir = 'uploads/documents';
        $absoluteDir = $this->projectDir . '/public/' . $relativeDir;
        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0775, true);
        }

        $relativePath = $relativeDir . '/' . $filename;
        $absolutePath = $absoluteDir . '/' . $filename;
        file_put_contents($absolutePath, $contents);

        return (new OrderDocument())
            ->setOrder($order)
            ->setType($type)
            ->setPath($relativePath)
            ->setUrl('/' . ltrim($relativePath, '/'))
            ->setHash(hash('sha256', $contents));
    }

    private function resolveBaseUrl(string $baseUrl): string
    {
        if ($baseUrl !== '') {
            return $baseUrl;
        }

        $request = $this->requestStack->getMainRequest() ?? $this->requestStack->getCurrentRequest();
        if ($request) {
            return $request->getSchemeAndHttpHost();
        }

        $defaultUri = $this->params->get('router.default_uri', '');
        if ($defaultUri !== '') {
            return (string) $defaultUri;
        }

        return $_ENV['DEFAULT_URI'] ?? 'https://technova.local';
    }
}
