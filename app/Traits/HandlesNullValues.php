<?php

namespace App\Traits;

trait HandlesNullValues
{
    /**
     * Recursively convert null values to empty strings in an array or object
     *
     * @param mixed $data
     * @return mixed
     */
    protected function convertNullsToEmptyStrings($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'convertNullsToEmptyStrings'], $data);
        }
        
        if (is_object($data)) {
            $array = json_decode(json_encode($data), true);
            return $this->convertNullsToEmptyStrings($array);
        }
        
        return $data === null ? '' : $data;
    }

    /**
     * Transform API response to replace nulls with empty strings
     *
     * @param mixed $data
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function jsonSuccessWithoutNulls($data, string $message = 'Success')
    {
        $transformedData = $this->convertNullsToEmptyStrings($data);
        
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $transformedData
        ]);
    }
}