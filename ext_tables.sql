create table tx_ximatypo3calendar_domain_model_entry (
	modified_fields text
);

create table be_users (
	tx_ximatypo3calendar_notify_review smallint(5) unsigned default 0 not null,
	tx_ximatypo3calendar_notify_live smallint(5) unsigned default 0 not null,
	tx_ximatypo3calendar_notify_categories int(11) unsigned default 0 not null
);
