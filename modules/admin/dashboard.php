<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipientEmail = trim($_POST['recipient_email'] ?? '');
    $recipientName = trim($_POST['recipient_name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) || $subject === '' || $body === '') {
        $message = 'Please enter a valid email, subject, and message.';
        $messageType = 'danger';
    } else {
        $mail = new PHPMailer(true);

        try {
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = $_ENV['HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['HOST_USER'];
            $mail->Password = $_ENV['HOST_PASSWORD'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = $_ENV['PORT'];

            $mail->setFrom($_ENV['HOST_USER'], 'My Ecommerce Website');
            $mail->addAddress($recipientEmail, $recipientName ?: $recipientEmail);
            $mail->addReplyTo($_ENV['HOST_REPLY_TO'], 'Scripting Language Session');

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = nl2br(htmlspecialchars($body));
            $mail->AltBody = $body;

            $mail->send();
            $message = 'Email sent successfully.';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Email could not be sent. ' . $mail->ErrorInfo;
            $messageType = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Email Sender</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <h1 class="h3 mb-2">PHP Email Sender</h1>
                        <p class="text-secondary mb-4">Send an email using PHPMailer and Gmail SMTP.</p>

                        <?php if ($message !== '') { ?>
                            <div class="alert alert-<?php echo $messageType; ?>">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php } ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="recipient_name" class="form-label">Recipient Name</label>
                                <input type="text" class="form-control" id="recipient_name" name="recipient_name" placeholder="Ram Bahadur Karki">
                            </div>

                            <div class="mb-3">
                                <label for="recipient_email" class="form-label">Recipient Email</label>
                                <input type="email" class="form-control" id="recipient_email" name="recipient_email" placeholder="example@gmail.com" required>
                            </div>

                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" value="PHP Email Test" required>
                            </div>

                            <div class="mb-4">
                                <label for="body" class="form-label">Message</label>
                                <textarea class="form-control" id="body" name="body" rows="6" required>Welcome to DAV BCA Email Session</textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Send Email</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>