<?php
// database/seeders/CmsSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{CmsPage,CmsMenu,CmsSetting};

class CmsSeeder extends Seeder {
    public function run(): void {
        CmsPage::updateOrCreate(['slug'=>'home'], [
            'title'=>['uk'=>'Головна'],
            'status'=>'published',
            'blocks'=>[
                ['type'=>'hero','data'=>['title'=>'Забронюйте поїздку зараз','subtitle'=>'Швидко та зручно']],
                ['type'=>'booking_form'],
                ['type'=>'benefits','data'=>['items'=>[
                    ['title'=>'Всі квитки онлайн','text'=>'Купівля за 2 хвилини'],
                    ['title'=>'Безпечна оплата','text'=>'Банківські шлюзи'],
                ]]],
                ['type'=>'how_it_works','data'=>['steps'=>[
                    ['title'=>'1. Виберіть напрямок','text'=>'Місто виїзду/прибуття'],
                    ['title'=>'2. Оберіть рейс','text'=>'Час, ціна, місця'],
                    ['title'=>'3. Оплатіть','text'=>'Отримайте e-квиток'],
                    ['title'=>'4. Готово','text'=>'Покажіть на посадці'],
                ]]],
                ['type'=>'trust_bar'],
                ['type'=>'help_cta','data'=>['text'=>'Потрібна допомога? Ми на звʼязку 24/7']],
                ['type'=>'faq','data'=>['items'=>[
                    ['q'=>'Чи треба друкувати квиток?','a'=>'Ні, достатньо на телефоні.'],
                ]]],
            ],
        ]);

        CmsMenu::updateOrCreate(['key'=>'header'], ['items'=>[
            ['title'=>['uk'=>'Головна'],'url'=>'/'],
            ['title'=>['uk'=>'Переваги'],'url'=>'#benefits'],
            ['title'=>['uk'=>'FAQ'],'url'=>'#faq'],
        ]]);

        CmsMenu::updateOrCreate(['key'=>'footer'], ['items'=>[
            ['title'=>['uk'=>'Пошук квитків'],'url'=>'#booking-form'],
            ['title'=>['uk'=>'Політика'],'url'=>'/policy'],
        ]]);

        CmsSetting::updateOrCreate(['key'=>'logo_url'], ['value'=>'/images/Asset-21.svg']);
        CmsSetting::updateOrCreate(['key'=>'phone'], ['value'=>'+380930510795']);
        CmsSetting::updateOrCreate(['key'=>'email'], ['value'=>'info@maxbus.com']);
    }
}
