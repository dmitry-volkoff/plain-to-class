<?php

declare(strict_types=1);

namespace Tests\DTO;

use ClassTransformer\Attributes\WritingStyle;

class WritingStyleEmpyDTO
{
    #[WritingStyle()]
    public string $contactFio;
    
    public string $contactEmail;
}
