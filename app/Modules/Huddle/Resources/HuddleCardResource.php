<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Resources;

use App\Modules\Huddle\DTOs\HuddleCardDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a HuddleCardDTO into the JSON shape the frontend expects.
 *
 * @mixin HuddleCardDTO
 */
class HuddleCardResource extends JsonResource
{
    /**
     * @param  HuddleCardDTO  $resource
     */
    public function __construct(HuddleCardDTO $resource)
    {
        // JsonResource expects $this->resource
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        /** @var HuddleCardDTO $dto */
        $dto = $this->resource;

        return [
            'source_type'      => $dto->sourceType,
            'source_id'        => $dto->sourceId,

            'patient' => [
                'id'    => $dto->patientId,
                'name'  => $dto->patientName,
                'alert' => $dto->patientAlert,
            ],

            'doctor_name'      => $dto->doctorName,

            'schedule' => [
                'date'     => $dto->date,
                'time'     => $dto->time
                    ? substr($dto->time, 0, 5)   // trim seconds → "09:30"
                    : null,
                'duration' => $dto->duration,
            ],

            'appointment' => [
                'type'     => $dto->appointmentType,
                'treatment'=> $dto->treatmentName,
                'category' => $dto->categoryName,
            ],

            'status'           => $dto->status,
            'chief_complaint'  => $dto->chiefComplaint,
            'notes'            => $dto->notes,

            'meta'             => $dto->meta,
        ];
    }
}
