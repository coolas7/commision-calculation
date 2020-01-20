# Paysera Commission task skeleton

Following steps:
- don't forget to change `Paysera` namespace and package name in `composer.json`
 to your own, as `Paysera` keyword should not be used anywhere in your task;
- `\Paysera\CommissionTask\Service\Math` is an example class provided for the skeleton and could or could not be used by your preference;
- needed scripts could be found inside `composer.json`;
- before submitting the task make sure that all the scripts pass (`composer run test` in particular);
- this file should be updated before submitting the task with the documentation on how to run your program.

Good luck! :) 


# DOCUMENTATION

Launch script:
- inside the skeleton-comission-task-master folder open the terminal.
- write command: `php index.php file.csv` (php index.php <your_file_name.csv>, csv file must be in <files> folder).

Test script: 
- inside the skeleton-comission-task-master folder open the terminal.
- write command: `composer run test` or `./vendor/phpunit/phpunit/phpunit --bootstrap vendor/autoload.php tests/Service/MathTest`

