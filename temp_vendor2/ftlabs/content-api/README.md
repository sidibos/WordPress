# FT Content API client

Example usage:

	use FTLabs\FTAPIConnection;
	use FTLabs\FTItem;
	
	$connection = new FTAPIConnection();
	
	$response = FTItem::get($connection, 'dd4725f6-06e4-11e1-90de-00144feabdc0');
