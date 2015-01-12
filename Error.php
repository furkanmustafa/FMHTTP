<?php

namespace FMHTTP;

class Error extends \Exception {
  
  const     NOT_FOUND       = 404;
  const     FORBIDDEN       = 403;
  const     BAD_REQUEST     = 400;
  const     SERVER_ERROR    = 500;
  
  protected $extra;
  
  function __construct($code, $message = null, $extra = null) {
    parent::__construct($message, $code);
    $this->extra = $extra;
  }
  
}
