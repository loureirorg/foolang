<?php
include_once( 'foolang.php' );
include_once( 'php_dic.php' );

/*
 * #break-continue
 * reinicia mecanismo break/continue
 */
function reset_break_continue()
{
	$GLOBALS['BREAK']		= 0;	
	$GLOBALS['CONTINUE']	= 0;
}


/*
 * #break-continue #alias #define #global #core #debug-step
 * reinicia memória do interpretador: variáveis globais, definições, ponteiros, 
 * etc.
 */
function reset_memory_context()
{
	$GLOBALS['LINE']		= 0;
	$GLOBALS['BREAK']		= 0;	
	$GLOBALS['CONTINUE']	= 0;
	$GLOBALS['ALIAS_MAP']	= array();	
    $GLOBALS['LST_DEFINE']	= array();
    $GLOBALS['VAR_GLOBAL']	= array();
}


/*
 * função auxiliar de exec_prg
 */
function desmembra_parametros( $SRC )
{
	// fl_exp( 'FNC_PARAMS' ) = FNC_PARAMS_VAZIO | FNC_PARAMS_CHEIO
	if ( fl_match( 'FNC_PARAMS_VAZIO', $SRC ) ) {
		return	array();
	}
	
	// fl_exp( 'FNC_PARAMS_CHEIO' ) = "(" + ESP_BRKS + ( VAR + ESP_BRKS + "," + ESP_BRKS ) * 0..n + VAR * 0..1 + ESP_BRKS + ")"
	$MAP_PARAM 		= fl_match( fl_exp( 'FNC_PARAMS_CHEIO' ), $SRC );
	
	// fl_exp( 'PARAM_SEQUENCE' ) = ( VAR + ESP_BRKS + "," + ESP_BRKS ) * 0..n
	$MAP_PARAM_LST	= fl_match( fl_exp( 'PARAM_SEQUENCE' ), $MAP_PARAM[ 3 ][ 0 ] );
	$LST_PARAM		= array_map( $MAP_PARAM_LST[ 1 ], create_function( '$ITEM', 'return	fl_match( "VAR", $ITEM );' ) );
	$LST_PARAM[]	= $MAP_PARAM[ 4 ][ 0 ];
	
	//
	return	$LST_PARAM;
}


/*
 * função auxiliar de exec_prg
 */
function desmembra_funcao( $SRC )
{
	// fl_exp( 'FNC' ) = @"function" + ESP_BRKS + VAR_NAME + ESP_BRKS + FNC_PARAMS + ESP_BRKS + CODE
	$MAP = fl_match( fl_exp( 'FNC' ), $SRC );
	return
		array(
			'NAME' 		=> $MAP[ 3 ],
			'CODE' 		=> $MAP[ 7 ],
			'LST_PARAM' => desmembra_parametros( $MAP[ 5 ] )
		)
	;
}


/*
 * #break-continue #core
 * executa programa
 */
function exec_prg( $CODE )
{
	reset_memory_context();
	
	// fl_exp( 'PRG' ) = ESP_BRKS + ( ( FNC | CODE ) + ESP_BRKS ) * 0..n
	$MAP_			= fl_match( fl_exp( 'PRG' ), $CODE );
	$LST_FNC_STR	= array_filter( $MAP[ 1 ], create_function( '$ITEM', 'return	fl_match( "FNC", $ITEM );' ) );
	$LST_CODE		= array_filter( $MAP[ 1 ], create_function( '$ITEM', 'return	fl_match( "CODE", $ITEM );' ) );
	
	$GLOBALS['LST_FNC']	= array_map( $LST_FNC_STR, desmembra_funcao() );
	$MAIN					= array_reduce( $LST_CODE );
	
	return	exec_block( "\{$MAIN\}", $GLOBALS['VAR_GLOBAL'] );
}


function exec_call( $SYNTAX, &$MEMO )
{
    // fl_exp( 'CALL' ) = VAR_NAME + ESP_BRKS + CALL_PARAMS
    reset_break_continue();
	
	$MAP 		= fl_match( fl_exp( 'CALL' ), $SYNTAX );
	$MAP_PARAMS = fl_match( fl_exp( 'CALL_PARAMS' ), $MAP[ 3 ] );	
	$LST_PARAMS = array_map( $MAP_PARAMS, exec_expression() );
	
    /* função do sistema */
    switch( $MAP[ 1 ] )
	{
		case 'printf':
			printf( $LST_PARAM[ 0 ] );
			break;
		case 'print_r':
			print_r( $LST_PARAM[ 0 ] );
			break;
		case 'define':
			global $LST_CONSTANT;
			$LST_CONSTANT[ $LST_PARAM[ 0 ] ] = $LST_PARAM[ 1 ];
			break;
	}
    
    /* função do usuário */
    return	exec_code( $GLOBALS['LST_FNC'][ $MAP[ 1 ] ], $LST_PARAMS );
}


function exec_array_item( $SYNTAX, &$MEMO )
{
	$MAP = fl_match( fl_exp( 'ARRAY_ITEM_WITH_KEY' ), $SYNTAX );
	if ( ! $MAP ) {
		return	array( 'KEY' => null, 'VALUE' => exec_expression( $SYNTAX, $MEMO ) );
	}
	
	// EXPRESSION + ESP_BRKS + "=>" + ESP_BRKS + EXPRESSION
	return	array( 'KEY' => $MAP[ 1 ], 'VALUE' => $MAP[ 5 ] );
}


function exec_array( $SYNTAX, &$MEMO )
{
	// fl_exp( 'ARRAY' ) = @"array" + ESP_BRKS + ( CALL_PARAMS_VAZIO | ARRAY_PARAMS_CHEIO )
    // fl_exp( 'ARRAY' ) = @"array" + ESP_BRKS + "(" + ESP_BRKS + ( ARRAY_ITEM + ESP_BRKS + "," + ESP_BRKS ) * 0..n + ARRAY_ITEM * 0..1 + ESP_BRKS + ")"
	$MAP	= fl_match( fl_exp( 'ARRAY' ), $SYNTAX );
	if ( fl_match( 'CALL_PARAMS_VAZIO', $MAP[ 3 ] ) ) {
		return	array();
	}
	
	$MAP_PARAM	= ;
	$ITEM = exec_array_item ( substr ( $PARAMS_SYNTAX, $POSITION, $POSITION_END - $POSITION ), $MEMO );
	if( $ITEM['KEY'] == null )
		$LST_PARAM[] = $ITEM['VALUE'];
	else
		$LST_PARAM[ $ITEM['KEY'] ] = $ITEM['VALUE'];
	
	
    // $POSITION = 0;
    // $POSITION_END = 0;
    
    // match_element ( $SYNTAX, $POSITION_END, '@"array" + ESP_BRKS + "(" + ESP_BRKS' );
    // $POSITION = $POSITION_END;
    
	// if( match_element ( $SYNTAX, $POSITION_END, 'CALL_PARAMS_VAZIO' ) ) {
		// return	array();
	// }
	
    // match_element ( $SYNTAX, $POSITION_END, '( ARRAY_ITEM + ESP_BRKS + "," + ESP_BRKS ) * 0..n + ARRAY_ITEM * 0..1' );
    // $PARAMS_SYNTAX = substr ( $SYNTAX, $POSITION, $POSITION_END - $POSITION );
    // $POSITION = $POSITION_END;
	
	// $POSITION = 0;
	// $POSITION_END = 0;
	
	// $FIM = false;
	// $LST_PARAM = array();
	// while ( ! $FIM )
	// {
		// match_element ( $PARAMS_SYNTAX, $POSITION_END, 'ESP_BRKS' );
		// $POSITION = $POSITION_END;
		
		// $FIM = ! match_element ( $PARAMS_SYNTAX, $POSITION_END, 'ARRAY_ITEM' );
		// if ( ! $FIM )
		// {
			// $ITEM = exec_array_item ( substr ( $PARAMS_SYNTAX, $POSITION, $POSITION_END - $POSITION ), $MEMO );
			// if( $ITEM['KEY'] == null )
				// $LST_PARAM[] = $ITEM['VALUE'];
			// else
				// $LST_PARAM[ $ITEM['KEY'] ] = $ITEM['VALUE'];
		// }
		// $POSITION = $POSITION_END;
		
		// $POSITION ++;
		// $POSITION_END ++;
		// if ( $POSITION >= strlen ( $PARAMS_SYNTAX ) )
			// $FIM = true;
	// }
	
	// return( $LST_PARAM );
}


function exec_code( $CODE, &$MEMO )
{
	if ( fl_match( 'BLOCK', $CODE ) ) {
		return	exec_block( $CODE, $MEMO );
	}
	
	return	exec_command( $CODE, $MEMO );
}


function exec_block( $CODE, &$MEMO )
{
	// "{" + ESP_BRKS + ( COMMAND + ESP_BRKS ) * 0..n + "}"
	reset_break_continue();
	
	$MAP = fl_match( fl_exp( 'BLOCK' ), $CODE );	
	
	return	array_walk( $MAP[ 3 ], exec_command() );
}


function exec_return( $CODE, &$MEMO )
{
    // RETURN           = "return" + ESP_BRKS + CALL_PARAM_ONE + ";"
    // CALL_PARAM_ONE   = "(" + ESP_BRKS + EXPRESSION * 0..1 + ESP_BRKS + ")"
    reset_break_continue();
	
	$MAP = fl_match( fl_exp( 'RETURN' ), $CODE );
	
    return	exec_expression( $MAP[ 3 ][ 0 ] );
}


function exec_if( $CODE, &$MEMO )
{
	// @"if" + ESP_BRKS + ( EXPRESSION ) + ESP_BRKS + ( CODE ) + ESP_BRKS + ( ELSEIF * 0..1 ) + ( ELSE * 0..1 )
	$MAP = fl_match( fl_exp( 'IF' ), $CODE );
	
	// if
	if ( exec_expression( $MAP[ 1 ], $MEMO ) ) {
		return	exec_code( $MAP[ 2 ] );
	}
	
	// elseif
	$N = 3;
	while ( fl_match( fl_exp( 'ELSEIF' ), $MAP[ $N ++ ] ) )
	{
		if ( exec_expression( $MAP[ $N ++ ], $MEMO ) ) {
			return	exec_code( $MAP[ 2 ] );
		}
	}
	
	// else
   	return	exec_code( $MAP[ $N ] );
}


function exec_for ( $CODE, &$MEMO )
{
	// "for" + ESP_BRKS + "(" + ESP_BRKS + EXPRESSION * 0..1 + ESP_BRKS + ";" + ESP_BRKS + EXPRESSION + ESP_BRKS + ";" + ESP_BRKS + EXPRESSION + ESP_BRKS + ")" + ESP_BRKS + CODE
	reset_break_continue();
	
	$POSITION_END = 0;
    
    match_element ( $CODE, $POSITION_END, '"for" + ESP_BRKS + "(" + ESP_BRKS' );
    $POSITION = $POSITION_END;

    match_element ( $CODE, $POSITION_END, 'EXPRESSION' );
    $INIT_EXPRESSION_STR = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    
    match_element ( $CODE, $POSITION_END, 'ESP_BRKS + ";" + ESP_BRKS' );
    $POSITION = $POSITION_END;
	
    match_element ( $CODE, $POSITION_END, 'EXPRESSION' );
    $EXPRESSION_STR = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    
    match_element ( $CODE, $POSITION_END, 'ESP_BRKS + ";" + ESP_BRKS' );
    $POSITION = $POSITION_END;
    
    match_element ( $CODE, $POSITION_END, 'EXPRESSION' );
    $INC_EXPRESSION_STR = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    
    match_element ( $CODE, $POSITION_END, 'ESP_BRKS + ")" + ESP_BRKS' );
    $POSITION = $POSITION_END;
    
	match_element ( $CODE, $POSITION_END, 'CODE' );
    $CODE_FOR = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    
	exec_expression( $INIT_EXPRESSION_STR, $MEMO );
	while( exec_expression( $EXPRESSION_STR, $MEMO ) )
	{
		if ( $CONTINUE > 0 )
			$CONTINUE --;
		else
			exec_code ( $CODE_FOR, $MEMO );
			
		if ( $BREAK > 0 )
		{
			$BREAK --;
			break;
		}
		
		exec_expression( $INC_EXPRESSION_STR, $MEMO );
	}
}

function exec_foreach( $CODE, &$MEMO )
{
	// FOREACH_SINGLE | FOREACH_COMPLEX
	if ( fl_match( 'FOREACH_SINGLE', $CODE ) ) {
		return	exec_foreach_single( $CODE, $MEMO );
	}
	
	return	exec_foreach_complex( $CODE, $MEMO );
}


function exec_foreach_complex( $CODE, &$MEMO )
{
	// "foreach" + ESP_BRKS + "(" + ESP_BRKS + EXPRESSION + ESP_BRKS + "as" + ESP_BRKS + VAR + ESP_BRKS + "=>" + ESP_BRKS + VAR + ESP_BRKS + ")" + ESP_BRKS + CODE
	reset_break_continue();
	
	$POSITION_END = 0;
    
    match_element ( $CODE, $POSITION_END, '"foreach" + ESP_BRKS + "(" + ESP_BRKS' );
    $POSITION = $POSITION_END;

    match_element ( $CODE, $POSITION_END, 'EXPRESSION' );
    $EXP_ARRAY = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    
    match_element ( $CODE, $POSITION_END, 'ESP_BRKS + "as" + ESP_BRKS' );
    $POSITION = $POSITION_END;
	
    match_element ( $CODE, $POSITION_END, 'VAR' );
    $VAR_INDEX = substr ( $CODE, $POSITION + 1, $POSITION_END - $POSITION - 1 );
    
    match_element ( $CODE, $POSITION_END, 'ESP_BRKS + "=>" + ESP_BRKS' );
    $POSITION = $POSITION_END;
    
    match_element ( $CODE, $POSITION_END, 'VAR' );
    $VAR_VALUE = substr ( $CODE, $POSITION + 1, $POSITION_END - $POSITION - 1 );
    
    match_element ( $CODE, $POSITION_END, 'ESP_BRKS + ")" + ESP_BRKS' );
    $POSITION = $POSITION_END;
    
	match_element ( $CODE, $POSITION_END, 'CODE' );
    $CODE_FOR = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );

	$EXP_ARRAY = exec_expression( $EXP_ARRAY, $MEMO );
	if( is_array( $EXP_ARRAY ) )
	{
		foreach( $EXP_ARRAY as $INDICE => $VALOR )
		{
			$MEMO[ $VAR_INDEX ] = $INDICE;
			$MEMO[ $VAR_VALUE ] = $VALOR;
			
			if ( $CONTINUE > 0 )
				$CONTINUE --;
			else
				exec_code( $CODE_FOR, $MEMO );
				
			if ( $BREAK > 0 )
			{
				$BREAK --;
				break;
			}
		}
	}
}


function exec_foreach_single( $CODE, &$MEMO )
{
	// "foreach" + ESP_BRKS + "(" + ESP_BRKS + EXPRESSION + ESP_BRKS + "as" + ESP_BRKS + VAR + ESP_BRKS + ")" + ESP_BRKS + CODE
	reset_break_continue();
	
	$POSITION_END = 0;
    
    match_element ( $CODE, $POSITION_END, '@"foreach" + ESP_BRKS + "(" + ESP_BRKS' );
    $POSITION = $POSITION_END;

    match_element ( $CODE, $POSITION_END, 'EXPRESSION' );
    $EXP_ARRAY = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    
    match_element ( $CODE, $POSITION_END, 'ESP_BRKS + @"as" + ESP_BRKS' );
    $POSITION = $POSITION_END;
	
    match_element ( $CODE, $POSITION_END, 'VAR' );
    $VAR_VALUE = substr ( $CODE, $POSITION + 1, $POSITION_END - $POSITION - 1 );
    
    match_element ( $CODE, $POSITION_END, 'ESP_BRKS + ")" + ESP_BRKS' );
    $POSITION = $POSITION_END;
    
	match_element ( $CODE, $POSITION_END, 'CODE' );
    $CODE_FOR = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );

	$EXP_ARRAY = exec_expression( $EXP_ARRAY, $MEMO );
	if( is_array( $EXP_ARRAY ) )
	{
		foreach( $EXP_ARRAY as $VALOR )
		{
			$MEMO[ $VAR_VALUE ] = $VALOR;
			
			if ( $CONTINUE > 0 )
				$CONTINUE --;
			else
				exec_code( $CODE_FOR, $MEMO );
				
			if ( $BREAK > 0 )
			{
				$BREAK --;
				break;
			}
		}
	}
}


function exec_while ( $CODE, &$MEMO )
{
	// "while" + ESP_BRKS + EXPRESSION + ESP_BRKS + CODE
	reset_break_continue();
	
	$POSITION_END = 0;
    
    match_element ( $CODE, $POSITION_END, '@"while" + ESP_BRKS' );
    $POSITION = $POSITION_END;
    
    match_element ( $CODE, $POSITION_END, 'EXPRESSION' );
    $EXPRESSION_STR = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    
    match_element ( $CODE, $POSITION_END, 'ESP_BRKS' );
    $POSITION = $POSITION_END;
    
    match_element ( $CODE, $POSITION_END, 'CODE' );
    $CODE_WHILE = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    
	while ( exec_expression ( $EXPRESSION_STR, $MEMO ) )
	{
		if ( $CONTINUE > 0 )
			$CONTINUE --;
		else
			exec_code ( $CODE_WHILE, $MEMO );
			
		if ( $BREAK > 0 )
		{
			$BREAK --;
			break;
		}
	}
}


function exec_dowhile ( $CODE, &$MEMO )
{
	// "do" + ESP_BRKS + BLOCK + ESP_BRKS + "while" + ESP_BRKS + EXPRESSION + ESP_BRKS + ";"
	reset_break_continue();

	$POSITION_END = 0;
    
    match_element ( $CODE, $POSITION_END, '@"do" + ESP_BRKS' );
    $POSITION = $POSITION_END;
    
    match_element ( $CODE, $POSITION_END, 'BLOCK' );
    $BLOCK = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );

    match_element ( $CODE, $POSITION_END, 'ESP_BRKS + @"while" + ESP_BRKS' );
    $POSITION = $POSITION_END;
    
    match_element ( $CODE, $POSITION_END, 'EXPRESSION' );
    $EXPRESSION_STR = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    
	do 
	{
		if ( $CONTINUE > 0 )
			$CONTINUE --;
		else
			exec_block ( $BLOCK, $MEMO );
			
		if ( $BREAK > 0 )
		{
			$BREAK --;
			break;
		}
	} while ( exec_expression ( $EXPRESSION_STR, $MEMO ) );
}


function exec_switch ( $CODE, &$MEMO )
{
	// "switch" + ESP_BRKS + EXPRESSION + ESP_BRKS + "{" + ESP_BRKS + ( "case" + ESP_BRKS + EXPRESSION + ESP_BRKS + ":" + ESP_BRKS + ( CODE + ESP_BRKS ) * 0..n ) * 0..n + ( "default" + ESP_BRKS + ":" + ESP_BRKS + ( CODE + ESP_BRKS ) * 0..n ) * 0..1 + ESP_BRKS + "}"
	global $BREAK;
	$BREAK = 0;

	$POSITION = 0;
	$POSITION_END = 0;
    
    match_element ( $CODE, $POSITION_END, '@"switch" + ESP_BRKS' );
    $POSITION = $POSITION_END;
    
    match_element ( $CODE, $POSITION_END, 'EXPRESSION' );
	$EXPRESSION = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
	$POSITION = $POSITION_END;
	
	match_element ( $CODE, $POSITION_END, 'ESP_BRKS + "{" + ESP_BRKS' );
    $POSITION = $POSITION_END;
    
    $LST_CASE = array ();
    while ( match_element ( $CODE, $POSITION_END, '@"case" + ESP_BRKS + EXPRESSION + ESP_BRKS + ":" + ESP_BRKS + ( CODE + ESP_BRKS ) * 0..n' ) )
    {
    	$POSITION_END = $POSITION;
    	
    	match_element ( $CODE, $POSITION_END, '@"case" + ESP_BRKS' );
    	$POSITION = $POSITION_END;
    	
    	match_element ( $CODE, $POSITION_END, 'EXPRESSION' );
    	$KEY = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    	$POSITION = $POSITION_END;
    	
    	match_element ( $CODE, $POSITION_END, 'ESP_BRKS + ":" + ESP_BRKS' );
    	$POSITION = $POSITION_END;
    	
    	$LST_CODE = array ();
    	while ( match_element ( $CODE, $POSITION_END, 'CODE + ESP_BRKS' ) )
    	{
    		$LST_CODE [] = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    		$POSITION = $POSITION_END;
		}
    
    	match_element ( $CODE, $POSITION_END, 'ESP_BRKS' );
    	$POSITION = $POSITION_END;
    	
    	$LST_CASE [] = array (
    		'KEY' => $KEY,
    		'CODE' => $LST_CODE
    	);
	}
	
	if ( match_element ( $CODE, $POSITION_END, '@"default" + ESP_BRKS + ":" + ESP_BRKS' ) )
	{
		$POSITION = $POSITION_END;
		
		$LST_CODE_DEFAULT = array ();
		while ( match_element ( $CODE, $POSITION_END, 'CODE + ESP_BRKS' ) )
		{
    		$LST_CODE_DEFAULT [] = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    		$POSITION = $POSITION_END;
		}
	}
	else
		$LST_CODE_DEFAULT = null;
	
	// busca case que bata com expressão do switch e executa cases subsequentes até encontrar um "break"
	$INDEX = 0;
	$EXECUTE = false;
	$RESULT = exec_expression ( $EXPRESSION, $MEMO );
	while ( ( $BREAK == 0 ) && ( $INDEX < count ( $LST_CASE ) ) )
	{
		if ( $RESULT == $LST_CASE [ $INDEX ] ['KEY'] )
			$EXECUTE = true;
		
		if ( $EXECUTE )
		{
			$INDEX_CODE = 0;
			while ( ( $BREAK == 0 ) && ( $INDEX < count ( $LST_CASE ) ) )
			{
				exec_code ( $LST_CASE [ $INDEX ] ['CODE'] [ $INDEX_CODE ], $MEMO );
				$INDEX_CODE ++;
			}
		}
			
		$INDEX ++;
	}
	
	if ( ( $BREAK == 0 ) && ( $LST_CODE_DEFAULT != null ) )
	{
		$INDEX = 0;
		while ( ( $BREAK == 0 ) && ( $INDEX < count ( $LST_CODE_DEFAULT ) ) )
		{
			exec_code ( $LST_CODE_DEFAULT [ $INDEX ], $MEMO );
			$INDEX ++;
		} 
		
		$BREAK = 0;
	}
	else
		$BREAK --;
}


function exec_break ( $CODE, &$MEMO )
{
	// "break" + ESP_BRKS + ( "(" + ESP_BRKS + EXPRESSION + ESP_BRKS + ")" + ESP_BRKS ) * 0..1 + ";"
	global $BREAK;
	$BREAK = 0;

	$POSITION = 0;
	$POSITION_END = 0;
    
    match_element ( $CODE, $POSITION_END, '@"break" + ESP_BRKS' );
    $POSITION = $POSITION_END;
    
    if ( match_element ( $CODE, $POSITION_END, '"(" + ESP_BRKS + EXPRESSION + ESP_BRKS + ")" + ESP_BRKS' ) )
    {
    	$POSITION_END = $POSITION;
    	
    	match_element ( $CODE, $POSITION_END, '"(" + ESP_BRKS' );
    	$POSITION = $POSITION_END;
    	
    	match_element ( $CODE, $POSITION_END, 'EXPRESSION' );
		$BREAK = substr ( $CODE, $POSITION, $POSITION_END - $POSITION ) + 0;
		$POSITION = $POSITION_END;
	}
	else
		$BREAK = 1;
}


function exec_continue ( $CODE, &$MEMO )
{
	// "continue" + ESP_BRKS + ( "(" + ESP_BRKS + EXPRESSION + ESP_BRKS + ")" + ESP_BRKS ) * 0..1 + ";"
	global $CONTINUE;
	$CONTINUE = 0;

	$POSITION = 0;
	$POSITION_END = 0;
    
    match_element ( $CODE, $POSITION_END, '@"continue" + ESP_BRKS' );
    $POSITION = $POSITION_END;
    
    if ( match_element ( $CODE, $POSITION_END, '"(" + ESP_BRKS + EXPRESSION + ESP_BRKS + ")" + ESP_BRKS' ) )
    {
    	$POSITION_END = $POSITION;
    	
    	match_element ( $CODE, $POSITION_END, '"(" + ESP_BRKS' );
    	$POSITION = $POSITION_END;
    	
    	match_element ( $CODE, $POSITION_END, 'EXPRESSION' );
		$CONTINUE = substr ( $CODE, $POSITION, $POSITION_END - $POSITION ) + 0;
		$POSITION = $POSITION_END;
	}
	else
		$CONTINUE = 1;
}


function exec_command ( $CODE, &$MEMO )
{
    // IF | WHILE | SWITCH | RETURN | ( EXPRESSION + ESP_BRKS + ";" ) | GLOBAL
    $POSITION = 0;
    
	if ( match_element ( $CODE, $POSITION, 'ECHO' ) )
	{
		$POSITION = 0;
		$POSITION_END = 0;
    die('ECHO !!!');
	    match_element ( $CODE, $POSITION_END, '@"echo" + ESP_BRKS' );
	    $POSITION_END = $POSITION;
	    
	    match_element ( $CODE, $POSITION_END, 'EXPRESSION' );
	    $EXPRESSION = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
		
		echo exec_expression( $EXPRESSION, $MEMO );
	}
		
	else if ( match_element ( $CODE, $POSITION, 'RETURN' ) )
		return ( exec_return ( $CODE, $MEMO ) );
        
    else if ( match_element ( $CODE, $POSITION, 'IF' ) )
        exec_if ( $CODE, $MEMO );
        
    else if ( match_element ( $CODE, $POSITION, 'FOREACH' ) )
        exec_foreach ( $CODE, $MEMO );
		
    else if ( match_element ( $CODE, $POSITION, 'FOR' ) )
        exec_for ( $CODE, $MEMO );
        
    else if ( match_element ( $CODE, $POSITION, 'WHILE' ) )
        exec_while ( $CODE, $MEMO );
        
    else if ( match_element ( $CODE, $POSITION, 'DOWHILE' ) )
        exec_dowhile ( $CODE, $MEMO );
        
    else if ( match_element ( $CODE, $POSITION, 'SWITCH' ) )
        exec_switch ( $CODE, $MEMO );
        
    else if ( match_element ( $CODE, $POSITION, 'BREAK' ) )
        exec_break ( $CODE, $MEMO );
        
    else if ( match_element ( $CODE, $POSITION, 'CONTINUE' ) )
        exec_continue ( $CODE, $MEMO );
        
    else if ( match_element ( $CODE, $POSITION, 'GLOBAL' ) )
	{
		$POSITION = 0;
    
	    match_element ( $CODE, $POSITION, '@"global" + ESP_BRKS + "$"' );
	    $POSITION_END = $POSITION;
	    
	    match_element ( $CODE, $POSITION_END, 'VAR_NAME' );
	    $VAR = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
	    
	    global $LST_GLOBAL;
	    $MEMO [ $VAR ] = &$LST_GLOBAL [ $VAR ];
	}
    //else if ( match_element ( $CODE, $POSITION_END, 'EXPRESSION + ESP_BRKS + ";"' ) )
    else
        exec_expression ( $CODE, $MEMO );    
}


function exec_expression( $CODE, &$MEMO )
{
	if ( fl_match( fl_exp( 'ASSIGN' ), $CODE ) ) {
        return	exec_assign( $CODE, $MEMO );
	}
        
    return	exec_math( $CODE, $MEMO );
}


function exec_assign( $CODE, &$MEMO )
{
    // ASSIGN_INC | ASSIGN_CTR | ASSIGN_NRM    
    if ( fl_match( 'ASSIGN_INC', $CODE ) ) {
        return	exec_assign_inc( $CODE, $MEMO );
	}
    
    if ( fl_match( 'ASSIGN_CTR', $CODE ) ) {
        return	exec_assign_ctr( $CODE, $MEMO );
	}
	
    return	exec_assign_nrm( $CODE, $MEMO );
}


function exec_assign_inc( $CODE, &$MEMO )
{
    // VAR + ESP_BRKS + "++"
    $POSITION = 1;
    $POSITION_END = 1;
    
    match_element ( $CODE, $POSITION_END, 'VAR_NAME' );
    $VAR_NAME = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    
    $MEMO [ $VAR_NAME ] ++;
    
    return ( $MEMO [ $VAR_NAME ] );
}


function exec_assign_ctr( $CODE, &$MEMO )
{
    // VAR + ESP_BRKS + OPR_ASSGN + ESP_BRKS + MATH
    $POSITION = 1;
    $POSITION_END = 1;
    
    match_element ( $CODE, $POSITION_END, 'VAR_NAME' );
    $VAR_NAME = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    
    match_element ( $CODE, $POSITION_END, 'ESP_BRKS' );
    $POSITION = $POSITION_END;
    
    match_element ( $CODE, $POSITION_END, 'OPR_ASSGN' );
    $OPR_ASSGN = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    $POSITION = $POSITION_END;
    
    match_element ( $CODE, $POSITION_END, 'ESP_BRKS' );
    $POSITION = $POSITION_END;
    
    match_element ( $CODE, $POSITION_END, 'MATH' );
    $MATH = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    $POSITION = $POSITION_END;
    
    $VALUE = exec_math ( $MATH, $MEMO );
    
    // ":=" | "+=" | "-=" | "*=" | "/=" | "%=" | "&=" | "|=" | "^=" | ">>=" | "<<="
    switch ( $OPR_ASSGN )
    {
        case ':=': $MEMO [ $VAR_NAME ] .= $VALUE; break;
        case '+=': $MEMO [ $VAR_NAME ] += $VALUE; break;
        case '-=': $MEMO [ $VAR_NAME ] -= $VALUE; break;
        case '*=': $MEMO [ $VAR_NAME ] *= $VALUE; break;
        case '/=': $MEMO [ $VAR_NAME ] /= $VALUE; break;
        case '%=': $MEMO [ $VAR_NAME ] %= $VALUE; break;
        case '&=': $MEMO [ $VAR_NAME ] &= $VALUE; break;
        case '|=': $MEMO [ $VAR_NAME ] |= $VALUE; break;
        case '^=': $MEMO [ $VAR_NAME ] ^= $VALUE; break;
        case '>>=': $MEMO [ $VAR_NAME ] >>= $VALUE; break;
        case '<<=': $MEMO [ $VAR_NAME ] <<= $VALUE; break;
    }
    
    return ( $MEMO [ $VAR_NAME ] );
}


function exec_assign_nrm( $CODE, &$MEMO )
{
    // VAR + ESP_BRKS + "=" + ESP_BRKS + MATH
    $POSITION = 0;
    $POSITION_END = 0;
    
    match_element ( $CODE, $POSITION_END, 'VAR' );
    $VAR_STR = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    
    match_element ( $CODE, $POSITION_END, 'ESP_BRKS' );
    $POSITION = $POSITION_END;
    
    match_element ( $CODE, $POSITION_END, '"="' );
    $POSITION = $POSITION_END;
    
    match_element ( $CODE, $POSITION_END, 'ESP_BRKS' );
    $POSITION = $POSITION_END;
    
    match_element ( $CODE, $POSITION_END, 'MATH' );
    $MATH = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    $POSITION = $POSITION_END;
    
    $RESULT = assign_var ( $VAR_STR, $MEMO, exec_math ( $MATH, $MEMO ) );
    
    return ( $RESULT );
}


function exec_math ( $CODE, &$MEMO )
{
    // 1. resolver os operandos: a * b * fnc (x) / 2 * 3 = 1 * 2 * 5 / 2 * 3
    // 1.5: resolver o operador de negação: !
    // 2. colocar em vetor tipo: operandos []; operadores [];
    // 3. para cada operando, ver se o operador é * ou / ou %, resolver, e substituir os 2 operadores por 1 (a resposta) no vetor
    // 4. igual ao item 3, mas operadores + -
    // 5. operadores comparativos < > <= >= == != <>
    // 6. operadores lógicos/binários: && || ^^ | & ^
    // OPERANDO + ESP_BRKS + ( OPR_MATH + ESP_BRKS + OPERANDO + ESP_BRKS ) * 0..n
    $LST_OPERANDO = array ();
    $LST_OPERADOR = array ();

    $POSITION = 0;
    $POSITION_END = 0;
    
    match_element ( $CODE, $POSITION_END, 'OPERANDO' );
    $OPERANDO_STR = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    $LST_OPERANDO [] = exec_operando ( $OPERANDO_STR, $MEMO );
    
    match_element ( $CODE, $POSITION_END, 'ESP_BRKS' );
    $POSITION = $POSITION_END;
    
    $FIM = false;
    while ( ! $FIM )
    {
        if ( ! match_element ( $CODE, $POSITION_END, 'OPR_MATH' ) )
        {
            $FIM = true;
            break;
        }
        $LST_OPERADOR [] = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    
        match_element ( $CODE, $POSITION_END, 'ESP_BRKS' );
        $POSITION = $POSITION_END;
        
        match_element ( $CODE, $POSITION_END, 'OPERANDO' );
        $OPERANDO_STR = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
        $LST_OPERANDO [] = exec_operando ( $OPERANDO_STR, $MEMO );
        $POSITION = $POSITION_END;
        
        match_element ( $CODE, $POSITION_END, 'ESP_BRKS' );
        $POSITION = $POSITION_END;
    }
    
    // * / %
    $INDICE = 0;
    while ( $INDICE < count ( $LST_OPERADOR ) )
    {
        $DEFAULT = false;
        switch ( $LST_OPERADOR [ $INDICE ] )
        {
            case '*': $LST_OPERANDO [ $INDICE ] *= $LST_OPERANDO [ $INDICE + 1 ]; break;
            case '/': $LST_OPERANDO [ $INDICE ] /= $LST_OPERANDO [ $INDICE + 1 ]; break;
            case '%': $LST_OPERANDO [ $INDICE ] %= $LST_OPERANDO [ $INDICE + 1 ]; break;
            default: 
                $DEFAULT = true; 
                $INDICE ++; 
            break;
        }        
        
        if ( ! $DEFAULT )
        {
            $LST_OPERANDO = array_merge ( array_slice ( $LST_OPERANDO, 0, $INDICE + 1 ), array_slice ( $LST_OPERANDO, $INDICE + 2 ) );
            $LST_OPERADOR = array_merge ( array_slice ( $LST_OPERADOR, 0, $INDICE ), array_slice ( $LST_OPERADOR, $INDICE + 1 ) );
            $INDICE = 0;
        }
    }
    
    // + -
    $INDICE = 0;
    while ( $INDICE < count ( $LST_OPERADOR ) )
    {
        $DEFAULT = false;
        switch ( $LST_OPERADOR [ $INDICE ] )
        {
            case '+': $LST_OPERANDO [ $INDICE ] += $LST_OPERANDO [ $INDICE + 1 ]; break;
            case '-': $LST_OPERANDO [ $INDICE ] -= $LST_OPERANDO [ $INDICE + 1 ]; break;
            case '.': $LST_OPERANDO [ $INDICE ] = $LST_OPERANDO [ $INDICE ] . $LST_OPERANDO [ $INDICE + 1 ]; break;
            default: 
                $DEFAULT = true; 
                $INDICE ++; 
            break;
        }        
        
        if ( ! $DEFAULT )
        {
            $LST_OPERANDO = array_merge ( array_slice ( $LST_OPERANDO, 0, $INDICE + 1 ), array_slice ( $LST_OPERANDO, $INDICE + 2 ) );
            $LST_OPERADOR = array_merge ( array_slice ( $LST_OPERADOR, 0, $INDICE ), array_slice ( $LST_OPERADOR, $INDICE + 1 ) );
            $INDICE = 0;
        }
    }

    // & | ^ && || ^^ << >>
    $INDICE = 0;
    while ( $INDICE < count ( $LST_OPERADOR ) )
    {
        $DEFAULT = false;
        switch ( $LST_OPERADOR [ $INDICE ] )
        {
            case '&': $LST_OPERANDO [ $INDICE ] = $LST_OPERANDO [ $INDICE ] & $LST_OPERANDO [ $INDICE + 1 ]; break;
            case '|': $LST_OPERANDO [ $INDICE ] = $LST_OPERANDO [ $INDICE ] | $LST_OPERANDO [ $INDICE + 1 ]; break;
            case '^': $LST_OPERANDO [ $INDICE ] = $LST_OPERANDO [ $INDICE ] ^ $LST_OPERANDO [ $INDICE + 1 ]; break;
            case '&&': $LST_OPERANDO [ $INDICE ] = $LST_OPERANDO [ $INDICE ] && $LST_OPERANDO [ $INDICE + 1 ]; break;
            case '||': $LST_OPERANDO [ $INDICE ] = $LST_OPERANDO [ $INDICE ] || $LST_OPERANDO [ $INDICE + 1 ]; break;
            case '^^': $LST_OPERANDO [ $INDICE ] = $LST_OPERANDO [ $INDICE ] ^ $LST_OPERANDO [ $INDICE + 1 ]; break;
            case '<<': $LST_OPERANDO [ $INDICE ] = $LST_OPERANDO [ $INDICE ] << $LST_OPERANDO [ $INDICE + 1 ]; break;
            case '>>': $LST_OPERANDO [ $INDICE ] = $LST_OPERANDO [ $INDICE ] >> $LST_OPERANDO [ $INDICE + 1 ]; break;
            default: 
                $DEFAULT = true; 
                $INDICE ++; 
            break;
        }        
        
        if ( ! $DEFAULT )
        {
            $LST_OPERANDO = array_merge ( array_slice ( $LST_OPERANDO, 0, $INDICE + 1 ), array_slice ( $LST_OPERANDO, $INDICE + 2 ) );
            $LST_OPERADOR = array_merge ( array_slice ( $LST_OPERADOR, 0, $INDICE ), array_slice ( $LST_OPERADOR, $INDICE + 1 ) );
            $INDICE = 0;
        }
    }
    
    // < > <= >= == != <> === !==
    $INDICE = 0;
    while ( $INDICE < count ( $LST_OPERADOR ) )
    {
        $DEFAULT = false;
        switch ( $LST_OPERADOR [ $INDICE ] )
        {
            case '<': $LST_OPERANDO [ $INDICE ] = $LST_OPERANDO [ $INDICE ] < $LST_OPERANDO [ $INDICE + 1 ]; break;
            case '>': $LST_OPERANDO [ $INDICE ] = $LST_OPERANDO [ $INDICE ] > $LST_OPERANDO [ $INDICE + 1 ]; break;
            case '<=': $LST_OPERANDO [ $INDICE ] = $LST_OPERANDO [ $INDICE ] <= $LST_OPERANDO [ $INDICE + 1 ]; break;
            case '>=': $LST_OPERANDO [ $INDICE ] = $LST_OPERANDO [ $INDICE ] >= $LST_OPERANDO [ $INDICE + 1 ]; break;
            case '==': $LST_OPERANDO [ $INDICE ] = $LST_OPERANDO [ $INDICE ] == $LST_OPERANDO [ $INDICE + 1 ]; break;
            case '===': $LST_OPERANDO [ $INDICE ] = $LST_OPERANDO [ $INDICE ] === $LST_OPERANDO [ $INDICE + 1 ]; break;
            case '!==': $LST_OPERANDO [ $INDICE ] = $LST_OPERANDO [ $INDICE ] !== $LST_OPERANDO [ $INDICE + 1 ]; break;
            case '!=': 
            case '<>': $LST_OPERANDO [ $INDICE ] = $LST_OPERANDO [ $INDICE ] != $LST_OPERANDO [ $INDICE + 1 ]; break;
            default: 
                $DEFAULT = true; 
                $INDICE ++; 
            break;
        }        
        
        if ( ! $DEFAULT )
        {
            $LST_OPERANDO = array_merge ( array_slice ( $LST_OPERANDO, 0, $INDICE + 1 ), array_slice ( $LST_OPERANDO, $INDICE + 2 ) );
            $LST_OPERADOR = array_merge ( array_slice ( $LST_OPERADOR, 0, $INDICE ), array_slice ( $LST_OPERADOR, $INDICE + 1 ) );
            $INDICE = 0;
        }
    }
    
    $RESULT = $LST_OPERANDO [ 0 ];
    return ( $RESULT );
}


function exec_var ( $CODE, &$MEMO )
{
	if ( substr ( $CODE, 0, 2 ) == '$$' )
	{
		$VAR = exec_var ( substr ( $CODE, 1, strlen ( $CODE ) - 1 ), $MEMO );
        $RESULT = exec_var ( '$' . $VAR, $MEMO );
        
        return ( $RESULT );
	}
	else
	{
		$POSITION = 1;
		match_element ( $CODE, $POSITION, 'VAR_NAME' );
		$LST_VAR = array ( substr ( $CODE, 1, $POSITION - 1 ) );
		$POSITION_END = $POSITION;

		$FIM = false;
		while ( ! $FIM )
		{
		    if ( match_element ( $CODE, $POSITION_END, 'ESP_BRKS + "[" + ESP_BRKS' ) )
		    {
		        $POSITION = $POSITION_END;
		        match_element ( $CODE, $POSITION_END, 'EXPRESSION' );
		        $MATH_STR = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
		        $MATH = exec_math ( $MATH_STR, $MEMO );
		        
		        match_element ( $CODE, $POSITION, 'ESP_BRKS + "]"' );
		        $POSITION = $POSITION_END;
		        
		        $LST_VAR [] = $MATH;
		    }
		    else
		        $FIM = true;
		}

		$RESULT = &$MEMO;
		foreach ( $LST_VAR as $VAR )
		    $RESULT = &$RESULT [ $VAR ];	
		
		return ( $RESULT );
	}
}


function exec_ternary( $CODE, $MEMO )
{
	// "(" + ESP_BRKS + EXPRESSION + ESP_BRKS + ")" + ESP_BRKS + "?" + ESP_BRKS + EXPRESSION + ESP_BRKS + ":" + ESP_BRKS + EXPRESSION
    $POSITION = 0;
    $POSITION_END = 0;
    
	match_element ( $CODE, $POSITION_END, '"(" + ESP_BRKS' );
    $POSITION = $POSITION_END;
	
    match_element ( $CODE, $POSITION_END, 'EXPRESSION' );
    $STR_EXPRESSION = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
	$POSITION = $POSITION_END;
    
    match_element ( $CODE, $POSITION_END, 'ESP_BRKS + ")" + ESP_BRKS + "?" + ESP_BRKS' );
    $POSITION = $POSITION_END;
    
    match_element ( $CODE, $POSITION_END, 'EXPRESSION' );
	$STR_TRUE = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    $POSITION = $POSITION_END;
    
    match_element ( $CODE, $POSITION_END, 'ESP_BRKS + ":" + ESP_BRKS' );
    $POSITION = $POSITION_END;
    
    match_element ( $CODE, $POSITION_END, 'EXPRESSION' );
    $STR_FALSE = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
    $POSITION = $POSITION_END;
    
	if( exec_expression( $STR_EXPRESSION, $MEMO ) )
		$RESULT = exec_expression( $STR_TRUE, $MEMO );
	else
		$RESULT = exec_expression( $STR_FALSE, $MEMO );
    
    return ( $RESULT );
}


function assign_var ( $CODE, &$MEMO, $VALUE )
{
	if ( substr ( $CODE, 0, 2 ) == '$$' )
	{
		$VAR = exec_var ( substr ( $CODE, 1, strlen ( $CODE ) - 1 ), $MEMO );
        $RESULT = assign_var ( '$' . $VAR, $MEMO, $VALUE );
        
        return ( $RESULT );
	}
	else
	{
		$POSITION = 1;
		match_element ( $CODE, $POSITION, 'VAR_NAME' );
		$LST_VAR = array ( substr ( $CODE, 1, $POSITION - 1 ) );
		$POSITION_END = $POSITION;

		$FIM = false;
		while ( ! $FIM )
		{
		    if ( match_element ( $CODE, $POSITION_END, 'ESP_BRKS + "[" + ESP_BRKS' ) )
		    {
		        $POSITION = $POSITION_END;
		        match_element ( $CODE, $POSITION_END, 'EXPRESSION' );
		        $MATH_STR = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
		        $MATH = exec_math ( $MATH_STR, $MEMO );
		        
		        match_element ( $CODE, $POSITION, 'ESP_BRKS + "]"' );
		        $POSITION = $POSITION_END;
		        
		        $LST_VAR [] = $MATH;
		    }
		    else
		        $FIM = true;
		}

		$RESULT = &$MEMO;
		foreach ( $LST_VAR as $VAR )
		    $RESULT = &$RESULT [ $VAR ];	
		
		$RESULT = $VALUE;
		
		return ( $RESULT );
	}
}


function exec_operando ( $CODE, &$MEMO )
{
    // NEGATIVE * 0..1 + ( ( "(" + ESP_BRKS + MATH + ESP_BRKS + ")" ) | VAR | NUMBER | STRING | CALL | ( "(" + ESP_BRKS + ASSIGN + ESP_BRKS + ") ) | @"true" | @"false" | CONSTANT )
    $POSITION = 0;
    $POSITION_END = 0;
    
    $NEGATIVE = match_element ( $CODE, $POSITION_END, 'NEGATIVE + ESP_BRKS' );
    $POSITION = $POSITION_END;
    
	if( match_element ( $CODE, $POSITION_END, 'TERNARY' ) )
	{
		// "(" + ESP_BRKS + EXPRESSION + ESP_BRKS + ")" + ESP_BRKS + "?" + ESP_BRKS + EXPRESSION + ESP_BRKS + ":" + ESP_BRKS + EXPRESSION
		$TERNARY = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
		$RESULT = exec_ternary( $TERNARY, $MEMO );
	}
    else if ( match_element ( $CODE, $POSITION_END, '( "(" + ESP_BRKS + MATH + ESP_BRKS + ")" )' ) )
    {
        match_element ( $CODE, $POSITION, '"(" + ESP_BRKS' );
        $POSITION_END = $POSITION;
        
        match_element ( $CODE, $POSITION_END, 'MATH' );
        $MATH = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
        
        $RESULT = ( exec_math ( $MATH, $MEMO ) );
    }
    else if ( match_element ( $CODE, $POSITION_END, 'VAR' ) )
    {
    	$RESULT = exec_var ( substr ( $CODE, 0, $POSITION_END ), $MEMO );
    }
    else if ( match_element ( $CODE, $POSITION_END, 'NUMBER' ) )
    {
        $NUMBER = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
        $RESULT = ( $NUMBER + 0 );
    }
    else if ( match_element ( $CODE, $POSITION_END, 'STRING' ) )
    {
        // tira as aspas
        $TIPO_ASPAS = substr ( $CODE, $POSITION, 1 );
        $STRING = substr ( $CODE, $POSITION + 1, $POSITION_END - $POSITION - 2 );
        if ( $TIPO_ASPAS == '"' )
            $RESULT = "$STRING";
        else
            $RESULT = ( $STRING . '' );
    }
	else if ( match_element ( $CODE, $POSITION, 'ARRAY' ) )
	{
		$RESULT = exec_array ( $CODE, $MEMO );
	}
    else if ( match_element ( $CODE, $POSITION_END, 'CALL' ) )
    {
        $CALL = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
        $RESULT = ( exec_call ( $CALL, $MEMO ) );
    }
    else if ( match_element ( $CODE, $POSITION_END, '@"true"' ) )
    {
        $RESULT = true;
    }
    else if ( match_element ( $CODE, $POSITION_END, '@"false"' ) )
    {
        $RESULT = false;
    }
    else if( match_element ( $CODE, $POSITION_END, 'CONSTANT' ) )
    {
    	$CONSTANT = substr ( $CODE, 0, $POSITION_END );
    	
    	global $LST_CONSTANT;
    	$RESULT = $LST_CONSTANT [ $CONSTANT ];
    }
    // if ( match_element ( $CODE, $POSITION_END, '( "(" + ESP_BRKS + ASSIGN + ESP_BRKS + ")" )' ) )
    else 
    {
        match_element ( $CODE, $POSITION_END, 'ESP_BRKS' );
        $POSITION = $POSITION_END;
        
        match_element ( $CODE, $POSITION_END, 'ASSIGN' );
        $ASSIGN = substr ( $CODE, $POSITION, $POSITION_END - $POSITION );
        
        $RESULT = ( exec_assign ( $ASSIGN, $MEMO ) );
    }
    
    if ( $NEGATIVE )
        return ( ! $RESULT );
    else
        return ( $RESULT );
}


global $PRG_REAL;
$PRG_REAL = null;
function exec_prg_real( $CODE )
{
	if( $FNC == null )
		$FNC = create_function( '', $CODE );
		
	$FNC();
}

$CODE = 
'$x = 9.0599060058594E-6;
printf("xxx".($x*10)."xxx");
';

/*$POSITION = 0;
$CODE = '9.0599060058594E-6';
echo match_element( $CODE, $POSITION, 'NUMBER');
die('');*/
$REAL_A = microtime ( true );
//for ( $i = 0; $i < 10; $i ++)
	//exec_prg_real ( $CODE );
$EMU_A = microtime ( true );
for ( $i = 0; $i < 1; $i ++)
	exec_prg ( $CODE );
$EMU_D = microtime ( true );

printf ( "Emulado: " . ( $EMU_D - $EMU_A ) . "<br>" );
printf ( "Real: " . ( $EMU_A - $REAL_A ) . "<br>" );
printf ( "Emulado = " . round ( ( ( $EMU_D - $EMU_A ) / ( $EMU_A - $REAL_A ) ) ). "x mais lento !<br>" );
?>