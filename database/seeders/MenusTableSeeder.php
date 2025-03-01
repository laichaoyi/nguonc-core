<?php

namespace nguonc\Core\Database\Seeders;

use Backpack\Settings\app\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use nguonc\Core\Models\Category;
use nguonc\Core\Models\Menu;
use nguonc\Core\Models\Region;
use nguonc\Core\Models\Catalog;
use nguonc\Core\Models\Theme;

class MenusTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $homeMenu = Menu::firstOrCreate(['name' => 'Trang chủ', 'link' => '/', 'type' => 'internal_link']);
        $categoryGroup = Menu::firstOrCreate(['name' => 'Thể loại', 'link' => '#', 'type' => 'internal_link']);
        $categories = Category::all();
        foreach ($categories as $category) {
            Menu::updateOrCreate([
                'name' => $category->name,
            ], [
                'link' => $category->getUrl(false),
                'type' => 'internal_link',
                'parent_id' => $categoryGroup->id
            ]);
        }

        $regionGroup = Menu::firstOrCreate(['name' => 'Quốc gia', 'link' => '#', 'type' => 'internal_link']);
        $regions = Region::all();
        foreach ($regions as $region) {
            Menu::updateOrCreate([
                'name' => $region->name,
            ], [
                'link' => $region->getUrl(false),
                'type' => 'internal_link',
                'parent_id' => $regionGroup->id
            ]);
        }
    }
}
