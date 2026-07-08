$schemas = ['vet_mi_clinica', 'vet_paws_care', 'vet_vet_amigos'];
foreach ($schemas as $schema) {
    $has = \Illuminate\Support\Facades\DB::selectOne(
        "select to_regclass(?) as t",
        [$schema.'.grooming_insumos']
    );
    $count = null;
    if ($has && $has->t) {
        try {
            \Illuminate\Support\Facades\DB::statement('SET search_path TO "'.$schema.'", public');
            $count = \Illuminate\Support\Facades\DB::selectOne('select count(*) as c from grooming_insumos')->c;
        } catch (\Throwable $e) {
            $count = 'ERR: '.$e->getMessage();
        }
    }
    echo $schema.' -> grooming_insumos exists: '.($has && $has->t ? 'YES' : 'NO').' | rows: '.var_export($count, true).PHP_EOL;
}
