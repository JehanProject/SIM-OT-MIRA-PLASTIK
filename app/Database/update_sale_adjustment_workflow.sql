USE mira_plastik;

ALTER TABLE piutang
    DROP CONSTRAINT chk_piutang_status,
    ADD CONSTRAINT chk_piutang_status
        CHECK (status IN ('OPEN', 'PARTIALLY_PAID', 'PAID', 'WRITTEN_OFF', 'ADJUSTED'));
