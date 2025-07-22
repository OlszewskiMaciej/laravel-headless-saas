<?php

namespace App\Modules\Auth\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid'              => $this->uuid,
            'name'              => $this->name,
            'email'             => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'roles'             => $this->roles->pluck('name'),
            'permissions'       => $this->getAllPermissions()->pluck('name'),
            'subscription'      => $this->whenLoaded('subscription', function () {
                if ($this->subscription()) {
                    return [
                        'name'    => $this->subscription()->name,
                        'status'  => $this->subscription()->stripe_status,
                        'ends_at' => $this->subscription()->ends_at,
                    ];
                }
                return null;
            }),
            'on_trial'      => $this->onTrial(),
            'trial_ends_at' => $this->trial_ends_at,
        ];
    }
}
