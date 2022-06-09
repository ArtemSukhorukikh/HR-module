<?php

namespace App\Dto\ApplicationPurchaseOfTraining;

use JMS\Serializer\Annotation as Serializer;

class ApplicationPurchaseOfTrainingDTO
{
    #[Serializer\Type("integer")]
    public int $id;

    #[Serializer\Type("integer")]
    public int $userId;

    #[Serializer\Type("string")]
    public string $description;

    #[Serializer\Type("string")]
    public string $link;

    #[Serializer\Type("string")]
    public string $note;

    #[Serializer\Type("integer")]
    public int $status;
}