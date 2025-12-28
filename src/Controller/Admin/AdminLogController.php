<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Enum\AuditAction;
use App\Service\AuditLoggerService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminLogController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.logs_dir%')] private readonly string $logsDir,
        private readonly AuditLoggerService $auditLogger,
        #[Autowire(service: 'monolog.logger.admin')]
        private readonly LoggerInterface $adminLogger,
    ) {
    }

    #[Route('/admin/logs', name: 'admin_logs', methods: ['GET'])]
    public function index(): Response
    {
        $logRoot = realpath($this->logsDir) ?: $this->logsDir;
        $files = [];

        foreach (new \DirectoryIterator($logRoot) as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $name = $file->getFilename();
            if (!str_ends_with($name, '.log')) {
                continue;
            }

            $files[] = [
                'name' => $name,
                'size' => $file->getSize(),
                'modifiedAt' => (new \DateTimeImmutable())->setTimestamp($file->getMTime()),
            ];
        }

        usort($files, static fn (array $a, array $b): int => $b['modifiedAt'] <=> $a['modifiedAt']);

        $this->auditLogger->log(action: AuditAction::AdminLogsView);
        $this->adminLogger->info('Admin logs viewed');

        return $this->render('admin/logs.html.twig', [
            'files' => $files,
        ]);
    }

    #[Route('/admin/logs/{name}', name: 'admin_logs_download', requirements: ['name' => '.+'], methods: ['GET'])]
    public function download(string $name): Response
    {
        $path = $this->resolveLogPath($name);

        $this->auditLogger->log(
            action: AuditAction::AdminLogsDownload,
            resource: 'log_file',
            data: [
                'name' => basename($path),
            ]
        );
        $this->adminLogger->info('Admin log downloaded', ['file' => basename($path)]);

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'text/plain');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($path)
        );

        return $response;
    }

    #[Route('/admin/logs/{name}/clear', name: 'admin_logs_clear', requirements: ['name' => '.+'], methods: ['POST'])]
    public function clear(string $name, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_logs_clear_'.$name, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $path = $this->resolveLogPath($name);
        if (!is_writable($path)) {
            throw $this->createAccessDeniedException('Log non modifiable.');
        }

        if (false === file_put_contents($path, '', LOCK_EX)) {
            throw $this->createAccessDeniedException('Echec du nettoyage du log.');
        }

        $this->auditLogger->log(
            action: AuditAction::AdminLogsClear,
            resource: 'log_file',
            data: [
                'name' => basename($path),
            ]
        );
        $this->adminLogger->info('Admin log cleared', ['file' => basename($path)]);

        $this->addFlash('success', 'Log nettoye.');

        return $this->redirectToRoute('admin_logs');
    }

    private function resolveLogPath(string $name): string
    {
        $logRoot = realpath($this->logsDir) ?: $this->logsDir;
        $safeName = basename($name);
        if (!str_ends_with($safeName, '.log')) {
            throw new NotFoundHttpException('Log introuvable.');
        }

        $path = realpath($logRoot.DIRECTORY_SEPARATOR.$safeName);
        if (false === $path || !str_starts_with($path, $logRoot.DIRECTORY_SEPARATOR)) {
            throw new NotFoundHttpException('Log introuvable.');
        }

        if (!is_file($path)) {
            throw new NotFoundHttpException('Log introuvable.');
        }

        return $path;
    }
}
