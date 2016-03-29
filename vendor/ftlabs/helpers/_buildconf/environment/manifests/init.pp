node /processing|lamp/ {
	cron { mailgun_dispatcher:
		ensure			=> present,
		command			=> "${env} && cd ${apppath}/helpers/mail/v1/daemons/ && ./mailgun_dispatcher --daemon",
		minute			=> '*/15'
	}
	cron { phperrortrack:
		ensure			=> absent
	}
}
node /web/ {
	cron { phperrortrack:
		ensure			=> absent
	}
}
