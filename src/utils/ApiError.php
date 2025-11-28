<?php

class ApiError extends Exception {
    public $statusCode;
    public $errors;

    public function __construct($statusCode, $message, $errors = []) {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->errors = $errors;
    }

    public function toArray() {
        $response = [
            'message' => $this->getMessage(),
        ];

        if (!empty($this->errors)) {
            $response['errors'] = $this->errors;
        }

        if (Env::get('nodeEnv') !== 'production') {
            $response['stack'] = $this->getTraceAsString();
        }

        return $response;
    }
}

