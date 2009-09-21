<?php
/********************************************************************************\
 * Copyright (C) Carl Taylor (cjtaylor@adepteo.com)                             *
 * Copyright (C) Torben Nehmer (torben@nehmer.net) for Code Cleanup             *
 *                                                                              *
 * This program is free software; you can redistribute it and/or                *
 * modify it under the terms of the GNU General Public License                  *
 * as published by the Free Software Foundation; either version 2               *
 * of the License, or (at your option) any later version.                       *
 *                                                                              *
 * This program is distributed in the hope that it will be useful,              *
 * but WITHOUT ANY WARRANTY; without even the implied warranty of               *
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                *
 * GNU General Public License for more details.                                 *
 *                                                                              *
 * You should have received a copy of the GNU General Public License            *
 * along with this program; if not, write to the Free Software                  *
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.  *
\********************************************************************************/

/**
 * 17/09/2009 - Fábio Rehm
 *
 * Fixing code to display timers description properly
 */

/// Enable multiple timers to aid profiling of performance over sections of code
class Profiler {
    private $description;
    private $startTime;
    private $endTime;
    private $initTime;
    private $initTime2;
    private $cur_timer;
    private $stack;
    private $trail;
    private $trace;
    private $count;
    private $running;
    private $log_dir;

    /**
    * Initialise the timer. with the current micro time
    */
    function Profiler( $output_enabled=false, $trace_enabled=false, $output_mode='html', $log_dir="")
    {
        $this->description = array();
        $this->startTime = array();
        $this->endTime = array();
        $this->initTime = 0;
        $this->cur_timer = "";
        $this->stack = array();
        $this->trail = "";
        $this->trace = "";
        $this->count = array();
        $this->running = array();
        $this->initTime2 = $this->getMicroTime();
	$this->initTime = $_SERVER['REQUEST_TIME'];
        $this->output_enabled = $output_enabled;
        $this->output_mode = $output_mode;
        $this->trace_enabled = $trace_enabled;
	$this->log_dir = $log_dir;
       	$this->startTime['Pre_Initialisation']=$this->initTime;
       	$this->endTime['Pre_Initialisation']=$this->initTime2;
       	$this->count['Pre_Initialisation']=1;
        $this->cur_timer='Pre_Initialisation';
        $this->description['Pre_Initialisation'] = 'Pre Initialisation';

        $this->startTimer('unprofiled');
    }

    // Public Methods

    /**
    *   Start an individual timer
    *   This will pause the running timer and place it on a stack.
    *   @param string $name name of the timer
    *   @param string optional $desc description of the timer
    */
    function startTimer($name, $desc="" ){
		if($this->trace_enabled)
		{
        	$this->trace.="start   $name\n";
        	$n=array_push( $this->stack, $this->cur_timer );
			if($this->cur_timer != $name){
        		$this->__suspendTimer( $this->stack[$n-1] );
        		$this->startTime[$name] = $this->getMicroTime();
			}
        	$this->cur_timer=$name;
		//$this->description[$name] = $desc;
        	$this->description[$name] = empty($desc) ? $name : $desc;
        	if (!array_key_exists($name,$this->count))
            	$this->count[$name] = 1;
        	else
            	$this->count[$name]++;
			}
    }

    /**
    *   Stop an individual timer
    *   Restart the timer that was running before this one
    *   @param string $name name of the timer
    */
    function stopTimer($name){
		if($this->trace_enabled)
		{
        	$this->trace.="stop   $name\n";
			if( $this->cur_timer == $name){
    	    	$this->endTime[$name] = $this->getMicroTime();
        		if (!array_key_exists($name, $this->running)){
            		$this->running[$name] = $this->elapsedTime($name);
        		}else
            		$this->running[$name] += $this->elapsedTime($name);
        		$this->cur_timer=array_pop($this->stack);
        		$this->__resumeTimer($this->cur_timer);
			}else{
        		$this->cur_timer=array_pop($this->stack);
				print("<pre>");
				debug_print_backtrace();
				print("</pre>");
				//die("Error stoping timer $name should have been ".$this->cur_timer);
			}
		}
    }

    /**
    *   measure the elapsed time of a timer without stoping the timer if
    *   it is still running
    */
    function elapsedTime($name){
        // This shouldn't happen, but it does once.
        if (!array_key_exists($name,$this->startTime))
            return 0;

        if(array_key_exists($name,$this->endTime)){
            return ($this->endTime[$name] - $this->startTime[$name]);
        } else {
            $now=$this->getMicroTime();
            return ($now - $this->startTime[$name]);
        }
    }//end start_time

    /**
    *   Measure the elapsed time since the profile class was initialised
    *
    */
    function elapsedOverall(){
        $oaTime = $this->getMicroTime() - $this->initTime;
        return($oaTime);
    }//end start_time

    /**
    *   print out a log of all the timers that were registered
    *
    */
    function printTimers($enabled=false)
    {

        if($this->log_dir||$this->output_enabled||$enabled){

			if(empty($output))$output=$this->output_mode;
            $TimedTotal = 0;
            $tot_perc = 0;

		    ob_start();

			$startup_time = $this->initTime2 - $this->initTime;
            $oaTime = $this->getMicroTime() - $this->initTime;
						if( $output == "text" ) {
							ksort($this->description);
							print("<pre>\n");
							echo"Initialisation took $startup_time seconds<br />\n";
							echo"============================================================================\n";
							echo "                              PROFILER OUTPUT\n";
							echo"============================================================================\n";
							print( "Calls                    Time  Routine\n");
							echo"-----------------------------------------------------------------------------\n";
						} 

						$percs = Array();

            while (list ($key, $val) = each ($this->description)) {
                $t = $this->elapsedTime($key);
                $total = $this->running[$key];
                $count = $this->count[$key];
                $TimedTotal += $total;
                $perc = ($total/$oaTime)*100;

//		$percs[] = Array( $perc, $key, $count, sprintf("%8.4f ms (%6.2f %%)", $total*1000, $perc));
		$percs[] = Array( $perc, $val, $count, sprintf("%8.4f ms (%6.2f %%)", $total*1000, $perc));

                $tot_perc+=$perc;
//                $out .= sprintf( "%3d    %8.4f ms (%6.2f %%)  %s\n", $count, $total*1000, $perc, $key);
                $out .= sprintf( "%3d    %8.4f ms (%6.2f %%)  %s\n", $count, $total*1000, $perc, $val);
								$c++;
            }

						if( $output == "text" ) {
							echo $out;
						}

						if( $output == "html" ) {
							rsort( $percs );

?>
<style type="text/css">
	.prf_h {
		background-color: black;
		color: white;
		font-weight: bold;
	}
	.prf_b {
		margin-left: auto;
		background-color: red; 
		height: 5px; 
	}
	.prf_t {
		background-color: #dddddd;
	}
</style>
<p>
<b>Profiled <?php echo $c ?> blocks:</b> Initialisation took <?php echo "$startup_time seconds" ?><br />
<table cellpadding="4" cellspacing="1" style="background-color: #eeeeee; border: 1px solid black;">
	<tr>
		<td class="prf_h">% badness</td>
		<td class="prf_h">timing</td>
		<td class="prf_h">block profiled</td>
		<td class="prf_h"># calls</td>
	</tr>

<?php
						$max = $percs[0][0];
						$scale = 100;
						foreach( $percs as $row ){
							$bar_length = ( $row[0] / $max ) * $scale;
							$bar_length = $bar_length < 1 ? 1 : $bar_length;
?>
	<tr><td class="prf_t"><div class="prf_b" style="width: <?php echo $bar_length ?>px;"></div></td><td><?php echo $row[3] ?></td><td><?php echo $row[1] ?></td><td><?php echo $row[2] ?></td></tr>
<?php
						}


	$missed=$oaTime-$TimedTotal;
	$perc = ($missed/$oaTime)*100;
	$tot_perc+=$perc;
	$bar_length = ( $perc / $max ) * $scale;
	$bar_length = $bar_length < 1 ? 1 : $bar_length;
?>

		<tr>
			<td class="prf_t"><div class="prf_b" style="width: <?php echo $bar_length ?>px;"></div></td>
			<td class="prf_t"><?php echo sprintf( "%8.4f ms (%6.2f %%)", $missed*1000,$perc) ?></td>
			<td colspan="2" class="prf_t">unprofiled code</td>
		</tr>

		<tr>
			<td class="prf_t"><div class="prf_b" style="width: <?php echo $bar_length ?>px;"></div></td>
			<td class="prf_t"><?php echo sprintf( "%8.4f ms (%6.2f %%)", $oaTime*1000,$tot_perc); ?></td>
			<td colspan="2" class="prf_t">overall elapsed time</td>
		</tr>
	</table>
</p>
<br>
<br>
<?php
							}

						if( $output == "text" ) {

							echo "\n";

							$missed=$oaTime-$TimedTotal;
							$perc = ($missed/$oaTime)*100;
							$tot_perc+=$perc;
							printf( "       %8.4f ms (%6.2f %%)  %s\n", $missed*1000,$perc, "Missed");

							echo"============================================================================\n";

							printf( "       %8.4f ms (%6.2f %%)  %s\n", $oaTime*1000,$tot_perc, "OVERALL TIME");

							echo"============================================================================\n";

							print("</pre>");

						}

						$out = ob_get_clean();
						echo $out;

						if(!empty($this->log_dir)){
							$log_dir=$this->log_dir;
							$ms=$this->getMicroSeconds();
							$outfile = "$log_dir/prof_" . date( "Y-m-d_H.i.s" ) . ".$ms." . ($output == "text" ? "txt" : $output);
							if( !$f = fopen( $outfile, "w" )) {
								echo "printTimers(): can't open $outfile";
							} else {
								fwrite( $f, $out );
								fclose( $f );
							}
						}
        }
    }

    function printTrace( $enabled=false )
    {
        if($this->trace_enabled||$enabled){
            print("<pre>");
            print("Trace\n$this->trace\n\n");
            print("</pre>");
        }
    }

    /// Internal Use Only Functions

    /**
    * Get the current time as accuratly as possible
    *
    */
    function getMicroTime(){
        $tmp=split(" ",microtime());
        $rt=$tmp[0]+$tmp[1];
        return $rt;
    }
    function getMicroSeconds(){
        $tmp=split(" ",microtime());
        $rt=$tmp[0];
        return $rt;
    }

    /**
    * resume  an individual timer
    *
    */
    function __resumeTimer($name){
		if($this->trace_enabled){
        	$this->trace.="resume  $name\n";
		}
        $this->startTime[$name] = $this->getMicroTime();
    }

    /**
    *   suspend  an individual timer
    *
    */
    function __suspendTimer($name){
		if($this->trace_enabled){
        	$this->trace.="suspend $name\n";
		}
        $this->endTime[$name] = $this->getMicroTime();
        if (!array_key_exists($name, $this->running))
            $this->running[$name] = $this->elapsedTime($name);
        else
            $this->running[$name] += $this->elapsedTime($name);
    }
}

function profiler_start($name) {
    //if (array_key_exists("adepteo_profiler",$_GLOBAL))
      //$_GLOBALS["adepteo_profiler"]->startTimer ($name);
}

function profiler_stop($name) {
    //if (array_key_exists("adepteo_profiler",$_GLOBAL))
      //$_GLOBALS["adepteo_profiler"]->stopTimer ($name);
}

?>
