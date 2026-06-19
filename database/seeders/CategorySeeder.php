<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Seed the default service categories.
     */
    public function run(): void
    {
        $categories = [
            'Design',
            'Development',
            'Marketing',
            'Writing',
            'Translation',
            'Video Editing',
            'Consulting',
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['slug' => Str::slug($category)],
                ['name' => $category]
            );
        }
    }
}
