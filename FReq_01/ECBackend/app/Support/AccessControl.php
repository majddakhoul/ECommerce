<?php

namespace App\Support;

final class AccessControl
{
    public const GUARD = 'api';

    public const ADMIN_ROLE = 'admin';

    public const USER_ROLE = 'user';

    public const ROLES = [self::ADMIN_ROLE, self::USER_ROLE];
}
