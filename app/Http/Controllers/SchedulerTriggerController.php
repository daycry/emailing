<?php

namespace App\Http\Controllers;

use App\Services\SchedulerActions;
use App\Support\SchedulerTriggerInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class SchedulerTriggerController extends Controller
{
    public function __invoke(Request $request, SchedulerActions $actions): JsonResponse
    {
        try {
            $input = SchedulerTriggerInput::fromJson($request->getContent(), (string) $request->header('Content-Type'));
        } catch (HttpException $error) {
            return new JsonResponse(['error' => $error->getMessage()], $error->getStatusCode(), ['Cache-Control' => 'no-store']);
        }

        try {
            return new JsonResponse($actions->run($input)->toArray(), 200, ['Cache-Control' => 'no-store']);
        } catch (Throwable) {
            // Never include connection errors, credentials, job bodies or traces.
            // A late failure may follow commits. This promises no rollback.
            return new JsonResponse(['error' => 'trigger_unavailable', 'work_outcome' => 'unknown', 'emails_completed' => null], 503, ['Cache-Control' => 'no-store', 'Retry-After' => '10']);
        }
    }
}
