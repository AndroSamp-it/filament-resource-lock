<?php

namespace Androsamp\FilamentResourceLock\Enums;

enum ForceTakeover: int
{
    case None = 0;
    case Save = 1;
    case NoSave = 2;
}
