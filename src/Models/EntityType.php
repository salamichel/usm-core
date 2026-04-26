<?php
declare(strict_types=1);

namespace App\Models;

enum EntityType: string
{
    case POST = 'post';
    case PAGE = 'page';
    case EQUIPE_SAISON = 'equipe_saison';
}
