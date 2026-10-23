<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Depot
    |--------------------------------------------------------------------------
    |
    | The mqmesh-depot git repository is the source of every Site file. This
    | site only ever reads from it: `git pull --ff-only` on a local clone,
    | never a commit or a push.
    |
    */

    'depot' => [
        'url' => env('MQMESH_DEPOT_URL', 'https://gitlab.com/wired420/mqmesh-depot.git'),
        'path' => env('MQMESH_DEPOT_PATH', storage_path('app/depot')),
        'webhook_secret' => env('MQMESH_WEBHOOK_SECRET', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Maximum upload size in kilobytes. The largest mesh in the depot is about
    | 94 MB, so 256 MB leaves plenty of room. PHP-FPM allows up to 1024 MB.
    |
    */

    'max_upload_kb' => (int) env('MQMESH_MAX_UPLOAD_KB', 262144),

    /*
    |--------------------------------------------------------------------------
    | File types
    |--------------------------------------------------------------------------
    |
    | Each zone can have one Site file per type, plus any number of community
    | versions. The download name is what MQ2Nav expects on disk.
    |
    */

    'types' => [
        'mesh' => [
            'label' => 'Mesh',
            'extension' => 'navmesh',
            'download_name' => '%s.navmesh',
        ],
        'doors' => [
            'label' => 'Doors',
            'extension' => 'json',
            'download_name' => '%s_doors.json',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Expansions
    |--------------------------------------------------------------------------
    |
    | In release order. The code matches the expansion column of zoneinfo.csv.
    |
    */

    'expansions' => [
        ['code' => 'classic', 'name' => 'Classic', 'year' => 1999],
        ['code' => 'kunark', 'name' => 'The Ruins of Kunark', 'year' => 2000],
        ['code' => 'sov', 'name' => 'The Scars of Velious', 'year' => 2000],
        ['code' => 'sol', 'name' => 'The Shadows of Luclin', 'year' => 2001],
        ['code' => 'pop', 'name' => 'The Planes of Power', 'year' => 2002],
        ['code' => 'loy', 'name' => 'The Legacy of Ykesha', 'year' => 2003],
        ['code' => 'ldon', 'name' => 'Lost Dungeons of Norrath', 'year' => 2003],
        ['code' => 'god', 'name' => 'Gates of Discord', 'year' => 2004],
        ['code' => 'oow', 'name' => 'Omens of War', 'year' => 2004],
        ['code' => 'don', 'name' => 'Dragons of Norrath', 'year' => 2005],
        ['code' => 'dodh', 'name' => 'Depths of Darkhollow', 'year' => 2005],
        ['code' => 'por', 'name' => 'Prophecy of Ro', 'year' => 2006],
        ['code' => 'tss', 'name' => "The Serpent's Spine", 'year' => 2006],
        ['code' => 'tbs', 'name' => 'The Buried Sea', 'year' => 2007],
        ['code' => 'sof', 'name' => 'Secrets of Faydwer', 'year' => 2007],
        ['code' => 'sod', 'name' => 'Seeds of Destruction', 'year' => 2008],
        ['code' => 'uf', 'name' => 'Underfoot', 'year' => 2009],
        ['code' => 'hot', 'name' => 'House of Thule', 'year' => 2010],
        ['code' => 'voa', 'name' => 'Veil of Alaris', 'year' => 2011],
        ['code' => 'rof', 'name' => 'Rain of Fear', 'year' => 2012],
        ['code' => 'cotf', 'name' => 'Call of the Forsaken', 'year' => 2013],
        ['code' => 'tds', 'name' => 'The Darkened Sea', 'year' => 2014],
        ['code' => 'tbm', 'name' => 'The Broken Mirror', 'year' => 2015],
        ['code' => 'eok', 'name' => 'Empires of Kunark', 'year' => 2016],
        ['code' => 'ros', 'name' => 'Ring of Scale', 'year' => 2017],
        ['code' => 'tbl', 'name' => 'The Burning Lands', 'year' => 2018],
        ['code' => 'tov', 'name' => 'Torment of Velious', 'year' => 2019],
        ['code' => 'cov', 'name' => 'Claws of Veeshan', 'year' => 2020],
        ['code' => 'tol', 'name' => 'Terror of Luclin', 'year' => 2021],
        ['code' => 'nos', 'name' => 'Night of Shadows', 'year' => 2022],
        ['code' => 'ls', 'name' => "Laurion's Song", 'year' => 2023],
        ['code' => 'tob', 'name' => 'The Outer Brood', 'year' => 2024],
        ['code' => 'sor', 'name' => 'Shattering of Ro', 'year' => 2025],
        ['code' => 'other', 'name' => 'Other', 'year' => null],
    ],

    /*
    |--------------------------------------------------------------------------
    | Expansion code fixes
    |--------------------------------------------------------------------------
    |
    | Typos found in zoneinfo.csv, mapped to the right code on import.
    |
    */

    'expansion_aliases' => [
        'othher' => 'other',
    ],

];
