<?php

namespace App\Modules\Subscription\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */    public function toArray($request)
    {        return [
            'id' => $this->id,
            'total' => $this->total(),
            'currency' => strtoupper($this->currency),
            'date' => Carbon::createFromTimestamp($this->date())->toIso8601String(),
            'status' => $this->status,
            'invoice_pdf' => $this->invoice_pdf,
            'number' => $this->number,
            'subscription_id' => $this->subscription,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
        ];
    }
}
