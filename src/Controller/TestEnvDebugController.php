<?php

namespace App\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TestEnvDebugController extends AbstractController
{
    #[Route('/_debug/env', name: 'app_debug_env', methods: ['GET'])]
    public function env(Request $request, ManagerRegistry $doctrine): JsonResponse
    {
        // Only expose this endpoint in test environment for safety
        $env = (string) ($this->getParameter('kernel.environment') ?? 'dev');
        if ($env !== 'test') {
            return $this->json(['error' => 'Not available'], 404);
        }

        $conn = $doctrine->getConnection();
        $params = $conn->getParams();
        $dbCurrent = null;
        try {
            $dbCurrent = $conn->fetchOne('SELECT DATABASE()');
        } catch (\Throwable $e) {
            $dbCurrent = null;
        }

        $script = $request->getScriptName(); // e.g. /index_test.php
        $isTestFrontController = str_contains($script, 'index_test.php');

        $user = $this->getUser();
        $userIdentifier = \is_object($user) && method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null;

        $payload = [
            'kernel_env' => $env,
            'app_env_server' => (string) $request->server->get('APP_ENV', ''),
            'front_controller' => $script,
            'uses_index_test_php' => $isTestFrontController,
            'doctrine_connection' => [
                'driver' => $params['driver'] ?? null,
                'host' => $params['host'] ?? null,
                'port' => $params['port'] ?? null,
                'dbname_param' => $params['dbname'] ?? null,
                'user' => $params['user'] ?? null,
                'database_function' => $dbCurrent, // result of SELECT DATABASE()
            ],
            'current_user' => $userIdentifier,
        ];

        return $this->json($payload);
    }
}
