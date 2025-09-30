<?php

namespace App\Http\Controllers\Admin\Config;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Config\CfgUsageCategory;
use App\Models\Configuration;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('应用设定值管理')]
class Configuration0Controller extends ConfigurationController
{
    public function __construct()
    {
        $this->usageCategory = CfgUsageCategory::APP;

        parent::__construct();
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request)
    {
        return parent::index($request);
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(Request $request, Configuration $configuration): Response
    {
        return parent::edit($request, $configuration);
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function editConfirm(Request $request, Configuration $configuration): Response
    {
        return parent::editConfirm($request, $configuration);
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, Configuration $configuration): Response
    {
        return parent::update($request, $configuration);
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(Configuration $configuration): Response
    {
        return parent::destroy($configuration);
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request): Response
    {
        return parent::create($request);
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function createConfirm(Request $request): Response
    {
        return parent::createConfirm($request);
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return parent::store($request);
    }
}
