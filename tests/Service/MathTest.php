<?php

declare(strict_types=1);

namespace VytautasUoga\CommissionTask\Tests\Service;

use PHPUnit\Framework\TestCase;
use VytautasUoga\CommissionTask\Service\Math;

class MathTest extends TestCase
{

	protected $math;


	public function setUp(): void
	{
		$this->math = new Math();

		$this->amount = 5;
		$this->currency = "USD";
		$this->user_type = "natural";
		$this->operation = "cash_out";
		$this->week_amount_eur = 1600;
	}


	public function getPayments()
	{
		$payments = [];

    	$math = new Math();
    	$payments[] = $math;

    	$math->date = "2019-05-05";
		$math->amount = 5;
		$math->currency = "USD";
		$math->user_type = "natural";
		$math->user_id = 7;
		$math->operation = "cash_out";


		$math = new Math();
    	$payments[] = $math;

    	$math->date = "2019-04-11";
		$math->amount = 14;
		$math->currency = "EUR";
		$math->user_type = "natural";
		$math->user_id = 3;
		$math->operation = "cash_out";


		$math = new Math();
    	$payments[] = $math;

    	$math->date = "2019-05-06";
		$math->amount = 206;
		$math->currency = "JPY";
		$math->user_type = "legal";
		$math->user_id = 7;
		$math->operation = "cash_out";


		$math = new Math();
    	$payments[] = $math;

    	$math->date = "2019-05-10";
		$math->amount = 206;
		$math->currency = "EUR";
		$math->user_type = "normal";
		$math->user_id = 7;
		$math->operation = "cash_out";

		return $payments;
	}


    public function testConvertToEur()
    {
 		$amount_eur = $this->amount;
		$amount_usd = $amount_eur/Math::USD_RATE;

        $this->assertEquals(
            $amount_usd,
            $this->math->convertToEur($amount_eur, $this->currency)
        );
    }


    public function testConvertFromEur()
    {
 		$amount_eur = $this->amount;
		$amount_usd = $amount_eur * Math::USD_RATE;

        $this->assertEquals(
            $amount_eur,
            $this->math->convertToEur($amount_usd, $this->currency)
        );
    }


    public function testCalculateCommission()
    {
    	$this->assertEquals(
            2.07,
            $this->math->calculateCommission($this->user_type, $this->operation, $this->amount, $this->currency, $this->week_amount_eur)
        );
    }


    public function testCheckCurrency()
    {
    	$this->assertTrue($this->math->checkCurrency($this->currency));
    }


    public function testRound_up()
    {
    	$this->assertEquals(
            8.07,
            $this->math->round_up(8.062344, 2)
        );
    }


    public function testGroupPaymentsByUser()
    {
    	$payments = $this->getPayments();

		foreach($payments as $payment) {

		    $uid = $payment->user_id;
		    $users[$uid][] = $payment;

		}


    	$this->assertEquals(
            $users,
            $this->math->groupPaymentsByUser($payments)
        );
    }


    public function testCheckWeekLimit()
    {
    	$payments = $this->getPayments();

		foreach($payments as $payment) {

		    $uid = $payment->user_id;
		    $users[$uid][] = $payment;
		}

	    foreach ($users as $user_id) {

	    	$sum = 0;
	    	$date1 = null;
	    	$week_day = 0;
	    	$cash_out_count = 0;

			// all user operations
	    	foreach ($user_id as $user) {

		    	if ($user->operation == 'cash_out') {

		    		// user cash out times
		    		$cash_out_count++;
		    	
			    	if ($date1 == null) {

			    		// set first cash out date and week day
			    		$date1 = new \DateTime($user->date);
		    			$week_day = date('w', strtotime($user->date));

		    			// make Sunday from 0 to 7
		    			if ($week_day == 0) {

		    				$week_day = 7;

		    			}

			    		$user->difference = 0;
			    		$sum = $this->math->convertToEur($user->amount, $user->currency);

			    		if ($sum > Math::CASH_OUT_WEEK_LIMIT) {

				    		$user->week_amount_eur = $sum;
				    		$sum = Math::CASH_OUT_WEEK_LIMIT;
			    			
			    		} else {

			    			$user->week_amount_eur = $sum;

			    		}

			    	} else {
			    		// other cash out dates and week days
			    		$date2 = new \DateTime($user->date);
		    			$week_day2 = date('w', strtotime($user->date));

		    			// make Sunday from 0 to 7
		    			if ($week_day2 == 0) {

		    				$week_day2 = 7;

		    			}

		    			// days between cash outs
			    		$interval = $date1->diff($date2);
						$user->difference = $interval->format('%a');

						// check if it is same week from Monday to Sunday
						if ($user->difference < 7 && $week_day2 >= $week_day) {

			    			$sum += $this->math->convertToEur($user->amount, $user->currency);

			    			if ($sum > Math::CASH_OUT_WEEK_LIMIT) {

			    				$user->week_amount_eur = $sum;
			    				$sum = Math::CASH_OUT_WEEK_LIMIT;

			    			} else if ($cash_out_count > Math::CASH_OUT_COUNT_LIMIT) {

			    				$user->week_amount_eur = $this->math->convertToEur($user->amount, $user->currency) + Math::CASH_OUT_WEEK_LIMIT;

			    			} else {

			    				$user->week_amount_eur = $sum;
			    			
			    			}

						} else {

							$sum = $this->math->convertToEur($user->amount, $user->currency);
				    		$user->week_amount_eur = $sum;

						}

						$date1 = $date2;
						$week_day = $week_day2;

			    	}

			    } else {

			    	$user->week_amount_eur = 0;
			    	$user->difference = 0;

			    }
	    	}
	    }

	    $this->assertEquals(
            $payments,
            $this->math->checkWeekLimit($payments)
        );
    }

}
