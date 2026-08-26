<?php
namespace App\Services;

use App\Models\AnatelImport;
use App\Models\Lista;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AnatelImporter
{
    public function __construct(private DomainNormalizer $normalizer, private AnatelExclusionMatcher $matcher) {}

    public function apply(Lista $lista, AnatelImport $import, array $payload): void
    {
        if (! $lista->isAnatel()) throw new RuntimeException('A lista informada não é ANATEL.');
        $file=$payload['files'][0]??[]; $raw=array_values(array_unique(array_filter($payload['domains']??[],'is_string')));
        $normalized=[]; $invalid=0;
        foreach ($raw as $candidate) { $domain=$this->normalizer->normalize($candidate); if ($domain===null) { $invalid++; continue; } $normalized[$domain]=true; }
        $domains=array_keys($normalized);
        $candidates=(int)($file['candidates']??count($raw));
        if ($domains===[] || ($candidates>=100 && count($domains)<($candidates*(float)config('anatel.blocked_ratio')))) {
            $import->update(['pages'=>(int)($file['pages']??0),'candidates_count'=>$candidates,'valid_count'=>count($domains),'invalid_count'=>$invalid+(int)($file['invalid']??0),'status'=>'blocked','error'=>'Extração vazia ou incoerente; nenhuma alteração aplicada.','finished_at'=>now()]);
            throw new RuntimeException('Importação bloqueada: extração vazia ou incoerente.');
        }
        $exclusions=$lista->anatelExclusions()->where('active',true)->get();
        $accepted=[]; $excluded=[];
        foreach ($domains as $domain) {
            if ($this->matcher->matches($domain,$exclusions)) $excluded[$domain]=true;
            else $accepted[$domain]=true;
        }
        $now=now(); $new=0; $existing=0; $reactivated=0; $unblocked=0;
        DB::transaction(function () use ($lista,$import,$accepted,$excluded,$now,&$new,&$existing,&$reactivated,&$unblocked) {
            foreach (array_chunk(array_keys($accepted),400) as $chunk) {
                $current=DB::table('dominios')->where('lista_id',$lista->id)->whereIn('dominio',$chunk)->get()->keyBy('dominio'); $rows=[];
                foreach ($chunk as $domain) {
                    $record=$current->get($domain);
                    if (!$record) { $new++; $rows[]=['lista_id'=>$lista->id,'dominio'=>$domain,'ativo'=>true,'inactive_reason'=>null,'last_anatel_import_id'=>$import->id,'created_at'=>$now,'updated_at'=>$now]; $result='new'; }
                    elseif (!$record->ativo && $record->inactive_reason!=='anatel_exclusion') { $reactivated++; DB::table('dominios')->where('id',$record->id)->update(['ativo'=>true,'inactive_reason'=>null,'last_anatel_import_id'=>$import->id,'updated_at'=>$now]); $result='reactivated'; }
                    else { $existing++; DB::table('dominios')->where('id',$record->id)->update(['last_anatel_import_id'=>$import->id]); $result='existing'; }
                    DB::table('anatel_import_domains')->insert(['anatel_import_id'=>$import->id,'domain'=>$domain,'result'=>$result,'created_at'=>$now,'updated_at'=>$now]);
                }
                if ($rows) DB::table('dominios')->insert($rows);
            }
            foreach (array_chunk(array_keys($excluded),400) as $chunk) {
                $existingRows=DB::table('dominios')->where('lista_id',$lista->id)->whereIn('dominio',$chunk)->get()->keyBy('dominio');
                foreach ($chunk as $domain) {
                    $record=$existingRows->get($domain);
                    if ($record && $record->ativo) { $unblocked++; DB::table('dominios')->where('id',$record->id)->update(['ativo'=>false,'inactive_reason'=>'anatel_exclusion','updated_at'=>$now]); }
                    DB::table('anatel_import_domains')->insert(['anatel_import_id'=>$import->id,'domain'=>$domain,'result'=>'excluded','created_at'=>$now,'updated_at'=>$now]);
                }
            }
        });
        $import->update(['pages'=>(int)($file['pages']??0),'candidates_count'=>$candidates,'valid_count'=>count($domains),'invalid_count'=>$invalid+(int)($file['invalid']??0),'new_count'=>$new,'existing_count'=>$existing,'reactivated_count'=>$reactivated,'excluded_count'=>count($excluded),'unblocked_count'=>$unblocked,'status'=>'completed','error'=>null,'finished_at'=>now()]);
    }
}
