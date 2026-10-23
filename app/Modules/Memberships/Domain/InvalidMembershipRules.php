<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain;

use DomainException;

final class InvalidMembershipRules extends DomainException {}
