<?php

declare(strict_types=1);

namespace VytautasUoga\CommissionTask\Service;

class Math
{
	// set constants
	const CASH_OUT_WEEK_LIMIT = 1000.00;
	const CASH_OUT_COUNT_LIMIT = 3;
	const USD_RATE = 1.1497;
	const JPY_RATE = 129.53;
	const CASH_IN_COMMISSION = 0.0003;
	const CASH_IN_MAX_COMMISSION_EUR = 5;
	const NATURAL_USER_COMMISSION = 0.003;
	const LEGAL_USER_COMMISSION = 0.003;
	const LEGAL_USER_MIN_COMMISION_EUR = 0.5;


    /**
     * create payment object
     * @param string $date
     * @param int $user_id
     * @param string $user_type
     * @param string $operation
     * @param float $amount
     * @param string $currency
     */
    public function setPayment(
    	string $date,
    	int $user_id,
    	string $user_type,
    	string $operation,
    	float $amount,
    	string $currency
    ) {
    	$this->date = $date;
    	$this->user_id = $user_id;
    	$this->user_type = $user_type;
    	$this->operation = $operation;
    	$this->amount = $amount;
    	$this->currency = $currency;
    }


    /**
     * The core app function
     * @param string $csv_filename
     *
     */
	public function doMath(string $csv_filename)
	{
		$payments = $this->scanCsvFile($csv_filename);

	    // check if cash out amount or operations per week is exceeded, and form new object with that info
	    if ($payments) {

	    	$payments = $this->checkWeekLimit($payments);


		    foreach ($payments as $payment) {

	        	$commission = $this->calculateCommission(
	        		$payment->user_type,
	        		$payment->operation,
	        		$payment->amount,
	        		$payment->currency,
	        		$payment->week_amount_eur
	        	);

	        	echo $commission. "\n";

		    }

	    } else {

	    	echo " Nėra mokėjimų \n";

	    }
	}


    /**
     * Scan file and put it to object
     * @param string $csv_filename
     * @return mixed
     */
	public function scanCsvFile(string $csv_filename)
	{
		if (($handle = fopen("files/".$csv_filename, "r")) !== false) {

			$payments = array();
			$row = 0;

			// scan data and put into object
		    while (($data = fgetcsv($handle, 1000, ",")) !== false) {

		    	$row++;

		     	$date 		= (string)$data[0];
		        $user_id	= (int)$data[1];
		        $user_type	= (string)$data[2];
		        $operation = (string)$data[3];
		        $amount 	= (float)$data[4];
		        $currency 	= (string)$data[5];

		        // creating payment object if currency is correct
		        if ($this->checkCurrency($currency) && 
		        	!empty($date) && 
		        	!empty($user_id) && 
		        	!empty($user_type) && 
		        	!empty($operation)  && 
		        	!empty($amount) && 
		        	!empty($currency)
		        ) {

			        $math = new Math();
			        $math->setPayment($date, $user_id, $user_type, $operation, $amount, $currency);
			        $payments[] = $math;

		    	} else {

	        		echo " Netinkama valiuta arba trūksta duomenų: eilute - ".$row."\n";

		    	}

		    }

		    fclose($handle);

		    return $payments;

		} else {

			echo "\n *** Wrong file name! ***\n";
			exit;

		}
	}
	

    /**
     * Group by user id
     * @param array $payments
     * @return array
     */
	public function groupPaymentsByUser(array $payments)
	{
		$users = array();

		foreach($payments as $payment) {

		    $uid = $payment->user_id;
		    $users[$uid][] = $payment;

		}

		return $users;
	}
	
	
    /**
     * check each payment week limits and cash out times per week
     * @param array $payments
     * @return array
     */
	public function checkWeekLimit(array $payments)
	{
		$users = $this->groupPaymentsByUser($payments);

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
			    		$sum = $this->convertToEur($user->amount, $user->currency);

			    		if ($sum > self::CASH_OUT_WEEK_LIMIT) {

				    		$user->week_amount_eur = $sum;
				    		$sum = self::CASH_OUT_WEEK_LIMIT;
			    			
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

			    			$sum += $this->convertToEur($user->amount, $user->currency);

			    			if ($sum > self::CASH_OUT_WEEK_LIMIT) {

			    				$user->week_amount_eur = $sum;
			    				$sum = self::CASH_OUT_WEEK_LIMIT;

			    			} else if ($cash_out_count > self::CASH_OUT_COUNT_LIMIT) {

			    				$user->week_amount_eur = $this->convertToEur($user->amount, $user->currency) + self::CASH_OUT_WEEK_LIMIT;

			    			} else {

			    				$user->week_amount_eur = $sum;
			    			
			    			}

						} else {

							$sum = $this->convertToEur($user->amount, $user->currency);
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

		return $payments;
	}
	
	
    /**
     * convert currency to eur
     * @param float $amount
     * @param string $currency
     * @return float
     */
	public function convertToEur(float $amount, string $currency)
	{
		switch ($currency) {
			case 'EUR':
				$amount_in_eur = $amount;
				break;
			case 'USD':
				$amount_in_eur = $amount/self::USD_RATE;
				break;		
			case 'JPY':
				$amount_in_eur = $amount/self::JPY_RATE;
				break;
			default:
				$amount_in_eur = 0;
				break;
		}

		return $amount_in_eur;
	}
	
	
    /**
     * convert currency from eur
     * @param float $amount
     * @param string $currency
     * @return float
     */
	public function convertFromEur(float $amount, string $currency)
	{
		switch ($currency) {
			case 'EUR':
				$amount = $amount;
				break;
			case 'USD':
				$amount = $amount * self::USD_RATE;
				break;		
			case 'JPY':
				$amount = $amount * self::JPY_RATE;
				break;
			default:
				$amount = 0;
				break;
		}

		return $amount;
	}

	
    /**
     * commission calculation for different user types
     * @param string $user_type
     * @param string $operation
     * @param float $amount
     * @param string $currency
     * @param float $week_amount_eur
     * @return float
     */
	public function calculateCommission(
		string $user_type,
	 	string $operation,
	 	float $amount,
	 	string $currency,
	 	float $week_amount_eur
	) {
		if ($operation == 'cash_in') {

			$commission = $amount * self::CASH_IN_COMMISSION;
			$commission = $this->convertToEur($commission, $currency);

			// set max 5eur cash in commission 
			if ($commission > self::CASH_IN_MAX_COMMISSION_EUR) {

				$commission = self::CASH_IN_MAX_COMMISSION_EUR;

			}

			$commission = $this->convertFromEur($commission, $currency);

		} else if ($operation == 'cash_out') {

		    if ($user_type == 'natural') {

		    	// check if week limit is exceeded
		    	if ($week_amount_eur > self::CASH_OUT_WEEK_LIMIT) {

		    		$commission = ($week_amount_eur - self::CASH_OUT_WEEK_LIMIT) * self::NATURAL_USER_COMMISSION;
					$commission = $this->convertFromEur($commission, $currency);

		    	}
		    	else {

		    		$commission = 0;

		    	}

		    }

		    if ($user_type == 'legal') {

		        $commission = $amount * self::LEGAL_USER_COMMISSION;
				$commission = $this->convertToEur($commission, $currency);

				// set mininimum commission for legal users
		        if ($commission < self::LEGAL_USER_MIN_COMMISION_EUR) {

		        	$commission = self::LEGAL_USER_MIN_COMMISION_EUR;

		        }

				$commission = $this->convertFromEur($commission, $currency);
		        
		    }

		} else {

			echo "Operacija neatpažinta";

		}

		$commission = $this->round_up($commission, 2);
		$commission = number_format($commission, 2, '.', "");

	    return $commission;
	}
	
	
    /**
     * checking currency
     * @param string $currency
     * @return bool
     */
	public function checkCurrency(string $currency)
	{
		if ($currency == "EUR" || $currency == "USD" || $currency == "JPY") {

		    return true;

		}

	    return false;
	}
	
	
    /**
     * Round up function
     * @param float $value
     * @param int $places
     * @return float
     */
	public function round_up(float $value, int $places = 0)
	{
		if ($places < 0) { $places = 0; }
		$mult = pow(10, $places);
	 	return ceil($value * $mult) / $mult;
	}

}
