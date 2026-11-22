<?php

return [
    /*
    | The automatic sync with the collection database (see App\Services\SyncRunner).
    */

    // how many records go to the collection database in one go (the schema and the data model are fetched once per go)
    'auto_chunk' => 25,

    // a run stops taking new records after this many seconds; what is left waits for the next one
    'auto_time_budget' => 2700,

    // the log of a run is cut to this many characters before it is stored
    'log_limit' => 200000,
];
