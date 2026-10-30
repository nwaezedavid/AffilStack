<?php

namespace App\Services\Leads;

use RuntimeException;

/**
 * A lead data provider (Apollo, Hunter) refused or failed a request. The
 * message is safe to show an admin; customers see a plain summary.
 */
class LeadSourceException extends RuntimeException {}
