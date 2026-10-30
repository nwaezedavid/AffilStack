<?php

namespace App\Services\Leads;

use RuntimeException;

/**
 * A lead run couldn't be started; the message is written for the customer.
 */
class LeadFinderException extends RuntimeException {}
