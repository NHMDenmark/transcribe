<?php

namespace App\Exceptions;

/** The sync cannot even start (no connection to the collection database, or the login was refused). */
class SyncAborted extends \RuntimeException
{
}
