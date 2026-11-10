<?php
declare(strict_types=1);
function sendAppMail(array $config,string $email,string $subject,string $body): void {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    $smtp=$config['smtp'];
    if(!in_array($smtp['encryption'],['tls','ssl'],true))throw new RuntimeException('SMTP TLS required');
    $mail=new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();$mail->SMTPDebug=0;$mail->Timeout=15;
    $mail->Host=$smtp['host'];$mail->Port=(int)$smtp['port'];$mail->SMTPAuth=true;
    $mail->Username=$smtp['username'];$mail->Password=$smtp['password'];$mail->SMTPSecure=$smtp['encryption'];$mail->CharSet='UTF-8';
    $mail->setFrom($smtp['from_email'],$smtp['from_name']);$mail->addAddress($email);$mail->Subject=$subject;$mail->Body=$body;$mail->send();
}
