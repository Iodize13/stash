<?php

namespace App\Services\Fetching;

/**
 * The URL (or a redirect target) points somewhere the server must never connect to.
 */
class UnsafeUrlException extends FetchException {}
