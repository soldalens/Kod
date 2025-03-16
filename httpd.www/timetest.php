<?php

$answerId = 199;

echo '<script type="text/javascript">';
echo 'var answerId = ' . json_encode($answerId) . ';'; 

// Open chart_create.php as a full page
echo 'window.location.href = "https://proanalys.se/chart_create.php?AnswerId=" + answerId;';

// After chart_create.php runs, it should include a redirect to phpWord_short2.php
echo '</script>';

?>