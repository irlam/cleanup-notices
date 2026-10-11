<?php
declare(strict_types=1);
$root=sys_get_temp_dir().'/notices-mail-'.bin2hex(random_bytes(8));mkdir($root,0700);mkdir($root.'/includes',0700);mkdir($root.'/bin',0700);
try{
 copy(dirname(__DIR__).'/bin/prepare-private-mail.php',$root.'/bin/prepare-private-mail.php');
 $fixture="<?php\n";
 foreach(['SMTP_HOST'=>'smtp.example.invalid','SMTP_PORT'=>465,'SMTP_USER'=>'fixture@example.invalid','SMTP_PASS'=>'disposable-fixture-only','SMTP_SECURE'=>'ssl','MAIL_FROM'=>'fixture@example.invalid','MAIL_FROM_NAME'=>'Test Notices']as$f=>$v)$fixture.='define('.var_export($f,true).','.var_export($v,true).");\n";
 file_put_contents($root.'/includes/mail.php',$fixture);
 $run=static function()use($root){$p=proc_open([PHP_BINARY,$root.'/bin/prepare-private-mail.php','--apply'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('Migration failed');if(str_contains($out.$err,'disposable-fixture-only'))throw new RuntimeException('Secret disclosed');};
 $run();$file=$root.'/includes/mail.local.php';$saved=require $file;
 if($saved['SMTP_PASS']!=='disposable-fixture-only'||$saved['enabled']!==true)throw new RuntimeException('Configuration not preserved');
 $hash=hash_file('sha256',$file);$run();if(hash_file('sha256',$file)!==$hash)throw new RuntimeException('Repeat changed configuration');
 echo "PASS: Existing mail settings migrate privately without disclosure; repeated migration preserves configuration.\n";
}finally{foreach(glob($root.'/includes/*')as$f)unlink($f);unlink($root.'/bin/prepare-private-mail.php');rmdir($root.'/includes');rmdir($root.'/bin');rmdir($root);}
