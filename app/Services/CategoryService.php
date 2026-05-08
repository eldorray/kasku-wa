<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Str;

class CategoryService
{
    /**
     * @param  array{label:string,emoji:string,type?:string,color:string,bg:string,slug?:?string}  $data
     */
    public function create(array $data): Category
    {
        $slug = $data['slug'] ?? Str::slug($data['label']);
        $base = $slug;
        $i = 2;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return Category::create([
            'slug' => $slug,
            'label' => $data['label'],
            'emoji' => $data['emoji'],
            'type' => $data['type'] ?? Category::TYPE_BOTH,
            'color' => $data['color'],
            'bg' => $data['bg'],
        ]);
    }

    /**
     * @param  array{label:string,emoji:string,type?:string,color:string,bg:string}  $data
     */
    public function update(Category $category, array $data): Category
    {
        $payload = [
            'label' => $data['label'],
            'emoji' => $data['emoji'],
            'color' => $data['color'],
            'bg' => $data['bg'],
        ];
        if (isset($data['type'])) {
            $payload['type'] = $data['type'];
        }
        $category->fill($payload)->save();

        return $category;
    }

    /**
     * @return array{ok:bool, error?:string, tx_count?:int}
     */
    public function delete(Category $category): array
    {
        $txCount = (int) $category->transactions()->count();
        if ($txCount > 0) {
            return ['ok' => false, 'error' => 'has_transactions', 'tx_count' => $txCount];
        }
        $category->delete();

        return ['ok' => true];
    }
}
