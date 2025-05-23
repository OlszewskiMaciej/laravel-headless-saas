<?php

namespace App\Modules\Admin\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'subject' => $this->whenLoaded('subject', function () {
                if ($this->subject) {
                    return [
                        'type' => get_class($this->subject),
                        'id' => $this->subject->id,
                        'name' => $this->subject->name ?? null,
                    ];
                }
                return null;
            }),
            'causer_type' => $this->causer_type,
            'causer_id' => $this->causer_id,
            'causer' => $this->whenLoaded('causer', function () {
                if ($this->causer) {
                    return [
                        'type' => get_class($this->causer),
                        'id' => $this->causer->id,
                        'name' => $this->causer->name ?? null,
                    ];
                }
                return null;
            }),
            'properties' => $this->properties,
            'created_at' => $this->created_at,
        ];
    }
}
