<?php

$name = pathinfo(__FILE__, PATHINFO_FILENAME);

include '../../cronjobs/init.php';

App::start($name);

use Email\Model\Mailer;

$error = '';
$message = '';

if (count($_POST)) {
    
    $post = $_POST;
    
    $email = $_POST['email'];
    
    if ($email) {

        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Въведете валиден имейл';
        } else {
            try {
                (new Mailer())->send('contacts', $email);
                
                $message = 'Съобщението е изпратено';
                
            } catch (Exception $e) {
                var_dump($e->getMessage());
            }
        }
        
        
    } else {
        
        $error = 'Моля попълнете полето';
    }
    
}

?>

<form method="post" action="">
	<label>Имейл адрес за изпращане</label>
	<input type="text" name="email" value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''?>" />
	
	<input type="submit" name="submit" value="Изпрати">
	<?php if($error) {echo '<br><span style="color:red">'.$error.'</span>';}?>
	<?php if($message) {echo '<br><span style="color:red">'.$message.'</span>';}?>
</form>