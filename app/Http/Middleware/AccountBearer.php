<?php

namespace App\Http\Middleware;

use App\Services\AccountKeyService;
use App\Support\ApiError;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class AccountBearer
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $keys = app(AccountKeyService::class);
            $candidate = $keys->candidate($request);
            $response = $keys->admittedOperation($candidate, function ($credential) use ($request, $next) {
                $request->attributes->set('account_credential', $credential);

                $response = $next($request);
                // Laravel's routing pipeline may render an exception before it reaches
                // this middleware. An error response must still roll back domain writes.
                if ($response->getStatusCode() >= 400) {
                    throw new ApiError(in_array($response->getStatusCode(), [400, 401, 403, 404, 409, 413, 415, 422, 429], true) ? $response->getStatusCode() : 503);
                }

                return $response;
            });
            $response->headers = new \App\Support\ApiNoStoreHeaders($response->headers->all());

            return $response;
        } catch (ApiError $error) {
            return $error->response();
        } catch (ValidationException) {
            return (new ApiError(422))->response();
        } catch (ModelNotFoundException) {
            return (new ApiError(404))->response();
        } catch (HttpExceptionInterface $error) {
            return (new ApiError(in_array($error->getStatusCode(), [400, 401, 403, 404, 409, 413, 415, 422, 429], true) ? $error->getStatusCode() : 503))->response();
        } catch (\Throwable) {
            // No bearer, client content, raw SQL or transport diagnostics enter logs/responses.
            return (new ApiError(503))->response();
        }
    }
}
