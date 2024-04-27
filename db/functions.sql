-- Create a function to compare views considering order and detailed differences.
CREATE OR REPLACE FUNCTION compare_views_with_order_and_diff(view1_name VARCHAR(50), view2_name VARCHAR(50))
RETURNS TABLE (
    norder BIGINT,
    is_row_correct BOOLEAN,
    is_in_solution TEXT,
    view1_row RECORD,
    view2_row RECORD
) AS $$
DECLARE
    miss VARCHAR := 'Falta/is_missing';
    extra VARCHAR := 'Sobra/is_extra';
    present VARCHAR := 'Está/is_present';
    match_count INTEGER;
    total_count INTEGER;
    squery TEXT;
BEGIN
    -- Count the number of records that match between views, considering the order.
    EXECUTE format('
        SELECT COUNT(*) FROM (
            SELECT * FROM %I
            EXCEPT
            SELECT * FROM %I
        ) AS diff
        UNION ALL
        SELECT COUNT(*) FROM (
            SELECT * FROM %I
            EXCEPT
            SELECT * FROM %I
        ) AS diff', view1_name, view2_name, view2_name, view1_name) INTO match_count;
    
    -- Count the total number of records in the first view.
    EXECUTE format('SELECT COUNT(*) FROM %I', view1_name) INTO total_count;
    
    -- If the number of matching records is equal to the total number of records, the views are equal.
    IF match_count = 0 THEN
        RETURN QUERY EXECUTE format('SELECT ROW_NUMBER() OVER () norder, TRUE, ''Está/is_present'', ROW(A.*), ROW(A.*) FROM %I AS A', view1_name);
    ELSE
        -- Return information on which records from the first view are present in the second view.
        squery := format('
            SELECT ROW_NUMBER() OVER () norder, 
                case when view1_rows = view2_rows then TRUE else FALSE END AS is_row_correct,
                case when view2_rows IS NULL then ''Sobra/is_extra'' WHEN view1_rows IS NULL THEN ''Falta/is_missing'' else ''Está/is_present'' END AS is_in_solution,
                CASE WHEN ROW(view1_rows.*) IS NULL THEN NULL ELSE ROW(view1_rows.*) END, 
                CASE WHEN ROW(view2_rows.*) IS NULL THEN NULL ELSE ROW(view2_rows.*) END   
            FROM (SELECT * FROM %I) AS view1_rows
            FULL JOIN (SELECT * FROM %I) AS view2_rows ON view1_rows = view2_rows', 
            view1_name, view2_name);
        RETURN QUERY EXECUTE squery;
    END IF;
END;
$$ LANGUAGE plpgsql;
