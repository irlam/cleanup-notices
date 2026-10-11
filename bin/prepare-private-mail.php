<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(($argv[1]??'')!=='--apply'||$argc!==2)exit(2);
ini_set('display_errors','0');
try{
 $file=dirname(__DIR__).'/includes/mail.local.php';
 if(is_link($file))throw new RuntimeException();
 if(is_file($file)){echo "Private mail configuration already exists; preserved.\n";exit;}
 require dirname(__DIR__).'/includes/mail.php';
 $config=['enabled'=>true];
 foreach(['SMTP_HOST','SMTP_PORT','SMTP_USER','SMTP_PASS','SMTP_SECURE','MAIL_FROM','MAIL_FROM_NAME']as$f){if(!defined($f)||(string)constant($f)==='')throw new RuntimeException();$config[$f]=constant($f);}
 $mask=umask(0077);try{$handle=fopen($file,'xb');}finally{umask($mask);}
 if(!$handle)throw new RuntimeException();
 try{$text="<?php\nreturn ".var_export($config,true).";\n";if(fwrite($handle,$text)!==strlen($text)||!fflush($handle))throw new RuntimeException();}finally{fclose($handle);}
 if(!chmod($file,0600))throw new RuntimeException();
 echo "Existing mail settings saved privately. No messages sent.\n";
}catch(Throwable $error){fwrite(STDERR,"Private mail migration failed. No messages sent.\n");exit(1);}
