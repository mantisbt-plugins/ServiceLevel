CREATE OR REPLACE VIEW view_status_times AS
	SELECT 
		b.id as bug_id,
		h.date_modified AS status_update_time,
		h.new_value as updated_to_status,
		b.status as current_status,
		b.date_submitted,
		h.date_modified - b.date_submitted as delta
        FROM mantis_bug_table b LEFT JOIN mantis_bug_history_table h
            ON b.id = h.bug_id 
            WHERE h.type=0 AND h.field_name='status'
            ORDER BY b.id ASC
