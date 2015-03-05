<?php

error_reporting(E_ALL);


/** Parse an Array to return Array Containing the Elements after the Given Offset. **/
function ParseArray($ARRAY, $Offset = 0)
{
	$NEW = array();
	for($index = $Offset; $index < count($ARRAY); $index++)
		array_push($NEW, $ARRAY[$index]);
		
	return $NEW;
}


function bytes2string($ARRAY, $Offset = 0)
{
	$string = "";
	for($index = $Offset; $index < count($ARRAY); $index++)
		$string .= pack("C", $ARRAY[$index]);
		
	return $string;
}

function string2bytes($STRING)
{
	$bin = unpack("C*", $STRING);

	return $bin;
}

/** Convert a 32 bit Unsigned Integer to Byte Vector using Bitwise Shifts. **/
function uint2bytes($UINT)
{
	$BYTES = array(0, 0, 0, 0);
	$BYTES[1] = $UINT >> 24;
	$BYTES[2] = $UINT >> 16;
	$BYTES[3] = $UINT >> 8;
	$BYTES[4] = $UINT;
				
	return $BYTES;
}
			
			
/** Convert a byte stream into unsigned integer 32 bit. **/	
function bytes2uint($BYTES, $nOffset = 0) { return ($BYTES[1 + $nOffset] << 24) + ($BYTES[2 + $nOffset] << 16) + ($BYTES[3 + $nOffset] << 8) + $BYTES[4 + $nOffset]; }
			
			
/** Convert a 64 bit Unsigned Integer to Byte Vector using Bitwise Shifts. **/
function uint2bytes64($UINT)
{
	$INTS = array(uint2bytes($UINT), uint2bytes($UINT >> 32));
	$BYTES = array(0, 0, 0, 0, 0, 0, 0, 0);
	
	for($nIndex = 1; $nIndex <= 8; $nIndex++)
	{
		if($nIndex < 4)
			$BYTES[$nIndex] = $INTS[1][$nIndex];
		else
			$BYTES[$nIndex] = $INTS[2][$nIndex - 4];
	}
				
	return $BYTES;
}

			
/** Convert a byte Vector into unsigned integer 64 bit. **/
function bytes2uint64($BYTES, $nOffset = 0)
{ 
	return (bytes2uint($BYTES, $nOffset) | (bytes2uint($BYTES, $nOffset + 4) << 32)); 
}


?>