<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Administrator = 'administrator';
    case Member = 'member';
    case Reader = 'reader';
}
