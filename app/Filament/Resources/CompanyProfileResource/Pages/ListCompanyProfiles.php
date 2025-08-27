<?php
// app/Filament/Resources/CompanyProfileResource/Pages/ListCompanyProfiles.php

namespace App\Filament\Resources\CompanyProfileResource\Pages;

use App\Filament\Resources\CompanyProfileResource;
use App\Models\CompanyProfile;
use Filament\Resources\Pages\Page;

class ListCompanyProfiles extends Page
{
    protected static string $resource = CompanyProfileResource::class;
    protected static string $view = 'filament.blank'; // будь-який порожній в'ю

    public function mount(): void
    {
        $id = CompanyProfile::query()->value('id')
            ?? CompanyProfile::query()->create([])->id;

        // редірект на edit
        $this->redirect(CompanyProfileResource::getUrl('edit', ['record' => $id]));
    }
}
