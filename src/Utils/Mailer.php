<?php

namespace App\Utils;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    public static function sendActivationEmail($toEmail, $toName, $activationToken) {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USER'];
            $mail->Password   = $_ENV['SMTP_PASS'];
            
            if ($_ENV['SMTP_PORT'] == 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            }
            
            $mail->Port = $_ENV['SMTP_PORT'];

            $mail->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
            $mail->addAddress($toEmail, $toName);


            $mail->isHTML(true);
            $mail->Subject = 'Aktivasi Akun - Sistem Peminjaman Inventaris Kampus';
            
            $frontendUrl = rtrim($_ENV['FRONTEND_URL'], '/');
            $activationLink = $frontendUrl . '/auth/activation?code=' . $activationToken;
            
            $mail->Body    = "
            <!DOCTYPE html>
            <html lang='id'>
            <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Aktivasi Akun</title>

            <style>
                body{
                    margin:0;
                    padding:0;
                    background:#f4f6f9;
                    font-family:Arial,Helvetica,sans-serif;
                    color:#333333;
                }

                .wrapper{
                    width:100%;
                    background:#f4f6f9;
                    padding:40px 15px;
                    box-sizing:border-box;
                }

                .container{
                    max-width:600px;
                    margin:0 auto;
                    background:#ffffff;
                    border-radius:12px;
                    overflow:hidden;
                    box-shadow:0 5px 20px rgba(0,0,0,.08);
                }

                .header{
                    background:#2563eb;
                    color:#ffffff;
                    text-align:center;
                    padding:35px 20px;
                }

                .header h1{
                    margin:0;
                    font-size:26px;
                }

                .content{
                    padding:35px;
                    line-height:1.7;
                    font-size:15px;
                }

                .content h2{
                    margin-top:0;
                    color:#111827;
                }

                .button-wrapper{
                    text-align:center;
                    margin:35px 0;
                }

                .btn{
                    display:inline-block;
                    background:#2563eb;
                    color:#ffffff !important;
                    text-decoration:none;
                    padding:14px 30px;
                    border-radius:8px;
                    font-weight:bold;
                    font-size:16px;
                }

                .btn:hover{
                    background:#1d4ed8;
                }

                .link-box{
                    margin-top:25px;
                    background:#f8fafc;
                    border:1px solid #e5e7eb;
                    padding:15px;
                    border-radius:8px;
                    word-break:break-all;
                    font-size:13px;
                }

                .footer{
                    padding:20px 35px 35px;
                    color:#6b7280;
                    font-size:13px;
                    line-height:1.6;
                }

                .copyright{
                    text-align:center;
                    margin-top:20px;
                    color:#9ca3af;
                    font-size:12px;
                }

                @media only screen and (max-width:600px){

                    .content{
                        padding:25px;
                    }

                    .footer{
                        padding:20px 25px 30px;
                    }

                    .btn{
                        width:100%;
                        box-sizing:border-box;
                    }

                    .header h1{
                        font-size:22px;
                    }
                }
            </style>

            </head>

            <body>

            <div class='wrapper'>

                <div class='container'>

                    <div class='header'>
                        <h1>Sistem Peminjaman Inventaris Kampus</h1>
                    </div>

                    <div class='content'>

                        <h2>Halo, {$toName}</h2>

                        <p>
                            Terima kasih telah melakukan pendaftaran akun.
                            Untuk mulai menggunakan sistem, silakan aktivasi akun Anda dengan menekan tombol di bawah ini.
                        </p>

                        <div class='button-wrapper'>
                            <a href='{$activationLink}' class='btn'>
                                Aktivasi Akun
                            </a>
                        </div>

                        <p>
                            Jika tombol di atas tidak dapat diklik, salin dan buka tautan berikut melalui browser:
                        </p>

                        <div class='link-box'>
                            <a href='{$activationLink}'>{$activationLink}</a>
                        </div>

                        <p>
                            Apabila Anda tidak pernah melakukan pendaftaran, Anda dapat mengabaikan email ini.
                        </p>

                    </div>

                    <div class='footer'>

                        <strong>Perhatian:</strong>

                        <p>
                            Demi keamanan akun, jangan bagikan tautan aktivasi ini kepada siapa pun.
                        </p>

                        <div class='copyright'>
                            © ".date('Y')." Sistem Peminjaman Inventaris Kampus
                        </div>

                    </div>

                </div>

            </div>

            </body>
            </html>
            ";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            return $mail->ErrorInfo; 
        }
    }

    public static function sendResetPasswordEmail($toEmail, $toName, $resetToken) {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USER'];
            $mail->Password   = $_ENV['SMTP_PASS'];
            
            if ($_ENV['SMTP_PORT'] == 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            }
            
            $mail->Port = $_ENV['SMTP_PORT'];

            $mail->setFrom($_ENV['SMTP_FROM_EMAIL'], $_ENV['SMTP_FROM_NAME']);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = 'Reset Password - Sistem Peminjaman Inventaris Kampus';
            
            $frontendUrl = rtrim($_ENV['FRONTEND_URL'], '/');
            $resetLink = $frontendUrl . '/auth/reset-password?token=' . $resetToken;
            
            $mail->Body    = "
            <!DOCTYPE html>
            <html lang='id'>
            <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Reset Password</title>

            <style>
                body{
                    margin:0;
                    padding:0;
                    background:#f4f6f9;
                    font-family:Arial,Helvetica,sans-serif;
                    color:#333333;
                }

                .wrapper{
                    width:100%;
                    background:#f4f6f9;
                    padding:40px 15px;
                    box-sizing:border-box;
                }

                .container{
                    max-width:600px;
                    margin:0 auto;
                    background:#ffffff;
                    border-radius:12px;
                    overflow:hidden;
                    box-shadow:0 5px 20px rgba(0,0,0,.08);
                }

                .header{
                    background:#2563eb;
                    color:#ffffff;
                    text-align:center;
                    padding:35px 20px;
                }

                .header h1{
                    margin:0;
                    font-size:26px;
                }

                .content{
                    padding:35px;
                    line-height:1.7;
                    font-size:15px;
                }

                .content h2{
                    margin-top:0;
                    color:#111827;
                }

                .button-wrapper{
                    text-align:center;
                    margin:35px 0;
                }

                .btn{
                    display:inline-block;
                    background:#2563eb;
                    color:#ffffff !important;
                    text-decoration:none;
                    padding:14px 30px;
                    border-radius:8px;
                    font-weight:bold;
                    font-size:16px;
                }

                .btn:hover{
                    background:#1d4ed8;
                }

                .link-box{
                    margin-top:25px;
                    background:#f8fafc;
                    border:1px solid #e5e7eb;
                    padding:15px;
                    border-radius:8px;
                    word-break:break-all;
                    font-size:13px;
                }

                .info-box{
                    margin-top:20px;
                    background:#fef3c7;
                    border:1px solid #f59e0b;
                    padding:12px 15px;
                    border-radius:8px;
                    font-size:13px;
                    color:#92400e;
                }

                .footer{
                    padding:20px 35px 35px;
                    color:#6b7280;
                    font-size:13px;
                    line-height:1.6;
                }

                .copyright{
                    text-align:center;
                    margin-top:20px;
                    color:#9ca3af;
                    font-size:12px;
                }

                @media only screen and (max-width:600px){

                    .content{
                        padding:25px;
                    }

                    .footer{
                        padding:20px 25px 30px;
                    }

                    .btn{
                        width:100%;
                        box-sizing:border-box;
                    }

                    .header h1{
                        font-size:22px;
                    }
                }
            </style>

            </head>

            <body>

            <div class='wrapper'>

                <div class='container'>

                    <div class='header'>
                        <h1>Sistem Peminjaman Inventaris Kampus</h1>
                    </div>

                    <div class='content'>

                        <h2>Halo, {$toName}</h2>

                        <p>
                            Kami menerima permintaan untuk mereset password akun Anda.
                            Klik tombol di bawah ini untuk membuat password baru.
                        </p>

                        <div class='button-wrapper'>
                            <a href='{$resetLink}' class='btn'>
                                Reset Password
                            </a>
                        </div>

                        <p>
                            Jika tombol di atas tidak dapat diklik, salin dan buka tautan berikut melalui browser:
                        </p>

                        <div class='link-box'>
                            <a href='{$resetLink}'>{$resetLink}</a>
                        </div>

                        <div class='info-box'>
                            ⏰ Link ini hanya berlaku selama <strong>1 jam</strong> sejak permintaan dibuat.
                        </div>

                        <p>
                            Apabila Anda tidak pernah meminta reset password, Anda dapat mengabaikan email ini. Password Anda tidak akan berubah.
                        </p>

                    </div>

                    <div class='footer'>

                        <strong>Perhatian:</strong>

                        <p>
                            Demi keamanan akun, jangan bagikan tautan reset password ini kepada siapa pun.
                        </p>

                        <div class='copyright'>
                            © ".date('Y')." Sistem Peminjaman Inventaris Kampus
                        </div>

                    </div>

                </div>

            </div>

            </body>
            </html>
            ";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            return $mail->ErrorInfo; 
        }
    }
}
