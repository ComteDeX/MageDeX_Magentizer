<?php
declare (strict_types=1);

namespace MageDeX\Magentizer\Console\FinalClasses;


final class SharedConstants
{
    public const RED="\033[31m";
    public const YELLOW="\033[33m";
    public const GREEN="\033[32m";
    public const BLUE="\033[34m";
    public const WHITE="\033[37m";
    public const COLOR_NONE="\e[0m";

    public const MODULE_SELF_NAME = 'MageDeX_Magentizer';
    public const VENDOR_NAME_ARGUMENT = "vendor's name";
    public const MODULE_NAME_ARGUMENT = "module's name";
    public const AUTHOR_NAME_ARGUMENT = "author's name";
}
