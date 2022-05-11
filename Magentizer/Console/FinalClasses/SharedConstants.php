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
    public const NEW_LINE_NO_STYLE= self::COLOR_NONE . "\n";

    public const MODULE_SELF_VENDOR = 'MageDeX';
    public const MODULE_SELF_MODULE_NAME = 'Magentizer';
    public const MODULE_SELF_FULLNAME = 'MageDeX_Magentizer';
    public const MODULE_SELF_DIRECTORY = 'MageDeX'. DIRECTORY_SEPARATOR .'Magentizer';

    public const OPTION_ALL_ARGUMENT = "--all";
    public const OPTION_ALL_ARGUMENT_SHORT = "-a";
    public const OPTION_OVERWRITE_FILE_ARGUMENT = "--overwrite";
    public const OPTION_OVERWRITE_FILE_ARGUMENT_SHORT = "-o";

    public const VENDOR_NAME_ARGUMENT = "vendor's name";
    public const MODULE_NAME_ARGUMENT = "module's name";
    public const AUTHOR_NAME_ARGUMENT = "author's name";
}
