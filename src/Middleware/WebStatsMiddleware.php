<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Service\WebTrackingService;

class WebStatsMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface  $request,
        RequestHandlerInterface $handler
    ): ResponseInterface
    {

        // Let Cake handle the request first
        $response = $handler->handle($request);

        // Track AFTER controller executed
        $tracker = new WebTrackingService();
        $tracker->track($request);

        return $response;
    }
}
