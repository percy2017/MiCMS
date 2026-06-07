<?php

namespace App\Http\Resources;

use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property MenuItem $resource
 */
class MenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'menu_id' => $this->resource->menu_id,
            'parent_id' => $this->resource->parent_id,
            'label' => $this->resource->label,
            'url' => $this->resource->url,
            'resolved_url' => $this->resource->resolvedUrl(),
            'type' => $this->resource->type,
            'page_id' => $this->resource->page_id,
            'order' => $this->resource->order,
            'target' => $this->resource->target,
            'is_external' => $this->resource->isExternal(),
            'children' => MenuItemResource::collection($this->whenLoaded('children')),
        ];
    }
}
