<?php
	include 'util.php';
	
	
	/** Enumeration Class to Specify the Byte Headers
		into a Human Readable Format. **/
	abstract class Requests
	{
			/** DATA PACKETS **/
			const GET_ACCOUNT        = 0;
			const GET_BLOCK          = 1;
			const BLOCK_LIST         = 2;
			const POOL_WEIGHT        = 3;
			const ACCOUNT            = 4;
			const BLOCK              = 5;
					
					
			/** REQUEST PACKETS **/
			const GET_BLOCK_LIST   = 129;
			const GET_POOL_WEIGHT  = 130;
			
			
			/** RESPONSE PACKETS. **/
			const FAILURE          = 200;
			
					
			/** GENERIC **/
			const CLOSE    = 254;
	}
	
	
	/** Create a Packet with Given Header. **/
	function GetPacket($TYPE)
	{
		$PACKET = new Packet();
		$PACKET->HEADER = $TYPE;
			
		return $PACKET;
	}
	
	
	/** Class to track the duration of time elapsed in seconds or milliseconds.
		Used for socket timers to determine time outs. **/
	class Timer
	{
		public $TIMER_START  = 0;
		public $TIMER_END    = 0;
		public $fStopped = false;
	
		public function Start() { $this->TIMER_START = microtime(true); $this->fStopped = false; }
		public function Reset() { $this->Start(); }
		public function Stop()  { $this->TIMER_END   = microtime(true); $this->fStopped = true; }
		
		
		/** Return the Total Seconds Elapsed Since Timer Started. **/
		public function Elapsed()
		{
			if($this->fStopped)
				return round(($this->TIMER_END - $this->TIMER_START) / 1000000);
				
			return round((microtime(true) - $this->TIMER_START) / 1000000);
		}
		
		/** Return the Total Milliseconds Elapsed Since Timer Started. **/
		public function ElapsedMilliseconds()
		{
			if($this->fStopped)
				return round(($this->TIMER_END - $this->TIMER_START) / 1000);
				
			return round((microtime(true) - $this->TIMER_START) / 1000);
		}
	}
	
	
	/** Class to handle sending and receiving of LLP Packets. **/
	class Packet
	{
		
	
		/** Components of an LLP Packet.
			BYTE 0       : Header
			BYTE 1 - 5   : Length
			BYTE 6 - End : Data      **/
		public   $HEADER = 255;
		public   $LENGTH = 0;
		public     $DATA = null;
		
		
		/** Set the Packet Null Flags. **/
		public function SetNull()
		{
			$this->HEADER   = 255;
			$this->LENGTH   = 0;
			$this->DATA = array();
		}
		
		
		/** Public Blank Constructor. **/
		function __construct()
		{
			$this->HEADER = 0;
			$this->LENGTH = 0;
			$this->DATA   = array();
		}
		
		
		/** Packet Null Flag. Header = 255. **/
		public function IsNull() { return ($this->HEADER == 255); }
		
		
		/** Determine if a packet is fully read. **/
		public function Complete() { return ($this->Header() && count($this->DATA) === $this->LENGTH); }
		
		
		/** Determine if header is fully read **/
		public function Header()
		{ 
			if($this->IsNull() === true)
				return false;
				
			if($this->HEADER < 128 && $this->LENGTH > 0)
				return true;
			
			if($this->HEADER >= 128 && $this->HEADER < 255 && $this->LENGTH == 0)
				return true;
				
			return false;
		}
		
		
		/** Sets the size of the packet from Bytes **/
		public function SetLength($BYTES)
		{
			$this->LENGTH =  ($BYTES[1] << 24);
			$this->LENGTH += ($BYTES[2] << 16);
			$this->LENGTH += ($BYTES[3] << 8 );
			$this->LENGTH +=  $BYTES[4];
		}
		
		
		/** Serializes class into a Byte Vector. Used to write Packet to Sockets. **/
		public function GetBytes()
		{
			$BYTES = array($this->HEADER);
			
			/** Handle for Data Packets. **/
			if($this->HEADER < 128)
			{
				array_push($BYTES, ($this->LENGTH >> 24), ($this->LENGTH >> 16), ($this->LENGTH >> 8), ($this->LENGTH));
				
				foreach($this->DATA as &$BYTE)
					array_push($BYTES, $BYTE);
			}
			
			return $BYTES;
		}
	}
	
	

	/** Base Template class to handle outgoing / incoming LLP data for both Client and Server. **/
	class Connection
	{
	
		/** Basic Connection Variables. **/
		private $TIMER;
		private $SOCKET;
		private $CONNECTED;


		/** Incoming Packet Being Built. **/
		public $INCOMING;
		public $IP;
		public $PORT;
		
		
		/** Connection Constructors **/
		public function __construct($address, $port)
		{
			$this->INCOMING  = new Packet();
			$this->TIMER     = new Timer();
			$this->SOCKET    = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			$this->CONNECTED = false;
			$this->IP        = $address;
			$this->PORT      = $port;
		}

		
		/** Connect the Socket to a Remote Connection. **/
		public function Connect()
		{
			$this->CONNECTED = socket_connect($this->SOCKET, $this->IP, $this->PORT); 
			
			if(!$this->CONNECTED)
				return false;
				
			return true;
		}
		
		
		/** Checks for any flags in the Error Handle. **/
		public function Errors() { return socket_last_error($this->SOCKET); }
				
				
		/** Determines if nTime seconds have elapsed since last Read / Write. **/
		public function Timeout($nTime){ return ($this->TIMER->Elapsed() >= $nTime); }
		
		
		/** Determines if Connected or Not. **/
		public function Connected()
		{
			if($this->CONNECTED === false)
				return false;
				
			return true;
		}
		
		
		/** Handles two types of packets, requests which are of header >= 128, and data which are of header < 128. **/
		public function PacketComplete(){ return $this->INCOMING->Complete(); }
		
		
		/** Used to reset the packet to Null after it has been processed. This then flags the Connection to read another packet. **/
		public function ResetPacket(){ $this->INCOMING->SetNull(); }
		
		
		/** Write a single packet to the TCP stream. **/
		public function WritePacket($PACKET) { $this->Write($PACKET->GetBytes()); }
		
		
		/** Blocking Packet Reader **/
		public function ReadPacket()
		{
			$read = $this->Read(1);
			$this->INCOMING->HEADER = $read[1];
			
			if($this->INCOMING->Complete() === false)
			{
				$this->INCOMING->SetLength($this->Read(4));
				$this->INCOMING->DATA = $this->Read($this->INCOMING->LENGTH);
			}
		}
		
		
		/** Disconnect the TCP Socket. **/
		public function Disconnect()
		{
			if($this->CONNECTED === false)
				return;
				
			socket_close($this->SOCKET);
			$this->CONNECTED = false;
		}
		
		
		/** Lower level network communications: Read. Interacts with OS sockets.
			No Loop Required as the Bytes Available is Checked before the Read. **/
		private function Read($nBytes)
		{
			if($this->Errors() === true || $this->Connected() === false)
				return "ERROR";
				
			//$this->TIMER->Reset();

			$read = socket_read($this->SOCKET, $nBytes);
			return string2bytes($read);
		}
							
				
		/** Lower level network communications: Write. Interacts with OS sockets. **/
		private function Write($DATA)
		{
			$Iterator = 0;
			
			/** Loop to write all contents of the packet to the stream in case the 
				Tcp Connection Fails to Write all the Data. [PHP Limitation]. **/
			while($Iterator < count($DATA))
			{
				if($this->Errors() === true || $this->Connected() === false)
					die("ERROR");
				
				$STRING = bytes2string(ParseArray($DATA, $Iterator));
				$Iterator += socket_write($this->SOCKET, $STRING, strlen($STRING));
			}
			
			//$this->TIMER->Reset();
		}
	}


?>
