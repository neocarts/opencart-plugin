<?php
namespace Neocarts;

/** status 0 = the Shop API could not be reached */
final class ApiError extends \RuntimeException
{
    /** @var int */
    public $status;
    /** @var array */
    public $errors;

    public function __construct(int $status, string $message, array $errors = [])
    {
        parent::__construct($message, $status);
        $this->status = $status;
        $this->errors = $errors;
    }
}
