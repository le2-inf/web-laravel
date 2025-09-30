<?php

// app/Attributes/PermissionAction.php

namespace App\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class PermissionAction
{
    public const string ADD     = 'add';
    public const string EDIT    = 'edit';
    public const string INDEX   = 'index';
    public const string SHOW    = 'show';
    public const string DELETE  = 'delete';
    public const string DOC     = 'doc';
    public const string APPROVE = 'approve'; // 审核

    public const string SETTING = 'setting';

    public const string INVOKE = 'invoke';
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
