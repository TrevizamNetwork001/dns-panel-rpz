<?php
namespace App\Jobs;
use App\Models\AnatelImport; use App\Models\AuditLog; use App\Services\AnatelImporter; use App\Services\AnatelPdfExtractor;
use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Foundation\Queue\Queueable; use Illuminate\Support\Facades\Storage;
class ProcessAnatelImport implements ShouldQueue {
 use Queueable; public int $timeout; public int $tries=1;
 public function __construct(public int $importId){$this->timeout=max(30,(int)config('anatel.extract_timeout')+30);}
 public function handle(AnatelPdfExtractor $extractor,AnatelImporter $importer):void{
  $import=AnatelImport::with('lista')->findOrFail($this->importId);$import->update(['status'=>'processing','progress'=>10,'started_at'=>now()]);
  try{$payload=$extractor->extract([Storage::disk('anatel')->path($import->storage_path)]);$import->update(['progress'=>70]);$importer->prepare($import->lista,$import,$payload);$this->audit($import,'anatel.import.preview_ready',"Prévia ANATEL pronta: {$import->fresh()->valid_count} válidos aguardando aprovação");$pending=$import->lista->anatelImports()->where('status','awaiting_approval')->count();$this->audit($import,'anatel.batch.preview_ready',"Lote ANATEL atualizado: {$pending} PDF(s) aguardando aprovação mestre");}
  catch(\Throwable $e){$import->refresh();if($import->status!=='blocked')$import->update(['status'=>'failed','progress'=>100,'error'=>'Falha no processamento do PDF.','finished_at'=>now()]);else$import->update(['progress'=>100]);$this->audit($import,$import->status==='blocked'?'anatel.import.blocked':'anatel.import.failed','Importação ANATEL não concluída');}
 }
 private function audit(AnatelImport $import,string $action,string $description):void{AuditLog::create(['user_id'=>$import->user_id,'empresa_id'=>$import->lista->empresa_id,'action'=>$action,'target_type'=>'anatel_import','target_id'=>$import->id,'description'=>$description,'ip_address'=>null,'created_at'=>now()]);}
}
