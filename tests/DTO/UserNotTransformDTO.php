<?php

declare(strict_types=1);

namespace Tests\DTO;

use ClassTransformer\Attributes\NotTransform;

class UserNotTransformDTO
{
    public string $fio;
    
    #[NotTransform()]
    public UserNotTransformRelationDTO $relation;
}
