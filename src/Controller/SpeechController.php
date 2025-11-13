<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Simple speech-to-text proxy endpoint for browsers without Web Speech API (e.g., Firefox).
 * If OPENAI_API_KEY is configured, audio is sent to Whisper API and the transcript is returned.
 */
#[Route('/api/speech-to-text', name: 'api_speech_to_text', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
class SpeechController extends AbstractController
{
    public function __invoke(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        /** @var UploadedFile|null $audio */
        $audio = $request->files->get('audio');
        if (!$audio instanceof UploadedFile || !$audio->isValid()) {
            return $this->json(['error' => 'No audio uploaded'], 400);
        }

        $apiKey = (string) ($_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? '');
        if ($apiKey === '') {
            return $this->json(['error' => 'Speech-to-text service is not configured. Please set OPENAI_API_KEY on the server.'], 501);
        }

        // Prepare multipart for Whisper API
        $mime = $audio->getMimeType() ?: 'audio/webm';
        $filename = $audio->getClientOriginalName() ?: ('audio.' . ($audio->guessExtension() ?: 'webm'));

        try {
            $response = $this->transcribeWithOpenAI($httpClient, $apiKey, $audio->getPathname(), $filename, $mime);
        } catch (\Throwable $exception) {
            return $this->json(['error' => 'Transcription failed: ' . $exception->getMessage()], 502);
        }

        // Whisper returns JSON with a 'text' field for the transcription
        $text = (string) ($response['text'] ?? '');
        if ($text === '') {
            return $this->json(['error' => 'Empty transcription result'], 502);
        }

        return $this->json(['transcript' => $text]);
    }

    /**
     * @param HttpClientInterface $http
     * @param string $apiKey
     * @param string $pathToFile
     * @param string $filename
     * @param string $mime
     * @return array<string,mixed>
     */
    private function transcribeWithOpenAI(HttpClientInterface $http, string $apiKey, string $pathToFile, string $filename, string $mime): array
    {
        $response = $http->request('POST', 'https://api.openai.com/v1/audio/transcriptions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
            ],
            'body' => [
                // Whisper expects multipart/form-data fields
                'file' => fopen($pathToFile, 'rb'),
                'model' => 'whisper-1',
                // optionally, you can set language hints or prompt
            ],
        ]);

        $status = $response->getStatusCode();
        $content = $response->getContent(false);
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('OpenAI error: HTTP ' . $status . ' ' . $content);
        }
        /** @var array<string,mixed> $data */
        $data = json_decode($content, true) ?: [];
        return $data;
    }
}
