<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConvertNullsToEmptyStrings
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only process JSON responses
        if ($response->headers->get('Content-Type') === 'application/json' || 
            str_contains($response->headers->get('Content-Type', ''), 'application/json')) {
            
            $content = $response->getContent();
            $data = json_decode($content, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                $transformedData = $this->convertNullsToEmptyStrings($data);
                $response->setContent(json_encode($transformedData));
            }
        }

        return $response;
    }

    /**
     * Recursively convert null values to empty strings
     */
    private function convertNullsToEmptyStrings($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'convertNullsToEmptyStrings'], $data);
        }
        
        return $data === null ? '' : $data;
    }
}