<?php

namespace BrunoNatali\Install;

interface InstallInterface
{
    const INSTALL_WRONG_PBIN = 0x10; // "Provide bin folder or setDir() first"
    const INSTALL_PBIN_ERROR = 0x11; // "pbin folder does not exist on: " . $this->pbin
    const INSTALL_WRONG_INS = 0x12; // "Instructions must be array or .json file path"
    const INSTALL_INS_ERROR = 0x13; // "Instructions file not found in: " . $instructions
    const INSTALL_INS_WRONG_FORMAT = 0x14; // "Instructions file in wrong format"
    const INSTALL_DIR_ERROR = 0x16; // "App name could not be determined before set dir"
    const INSTALL_SYS_BIN_WRONG_FORMAT = 0x17; // "'sys-bin-files' must be array"
    const INSTALL_SYMLINK_ERROR = 0x18; // "Error while installing '$bin': " . (\is_array($out) ? implode(PHP_EOL, $out) : $out)
    const INSTALL_SERVICE_WRONG_FORMAT = 0x19; // "'service' must be array"
    const INSTALL_SERVICE_CONFIG_ERROR = 0x1A; // "Config error in service index $key"
    const INSTALL_POST_INST_FILE_ERROR = 0x1B; // "Post installation file not exist"
    const INSTALL_INS_REQUIRE_ERROR = 0x1C; // "Require must be array"

    public function setDir(string $dir);

    public function setPBin(string $pbin = null);

    public function setInstructions($instructions = false);

    public function getAppName(): string;

    public function install();
}