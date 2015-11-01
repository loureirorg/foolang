<?php
$GLOBALS[ 'LST_WORD' ] = array ();

// FooLang Match: fun��o que quebra cada senten�a em um registro de vetor.
// s� retorna grupos entre (). Funcionamento igual a preg_match
function fl_match( $FL_PATTERN, $SUBJECT )
{
	match_element( $SUBJECT, 0, $FL_PATTERN );
}


// significado de uma palavra fooLang
function fl_get_word( $NAME_WORD )
{
	return	$GLOBALS[ 'LST_WORD' ][ $NAME_WORD ];
}


// define palavra fooLang
function fl_set_word( $NAME_WORD, $SYNTAX )
{
	$GLOBALS[ 'LST_WORD' ][ $NAME_WORD ] = $SYNTAX;
}


$GLOBALS[ 'CACHE_' ] = array();
// indice 1 = md5 da string
// indice 2 = md5 da sintaxe
// valor    = array ( v ou f, diff posi��o )

function match_element( $STRING, &$POSITION, $SYNTAX ) 
{
	// dica para otimiza��o: usar padr�o "diminui��o de gerenciamento de dados" = n�o � necess�rio fazer um md5 de position at� o final da linha
	$MD5_STRING = md5( substr( $STRING, $POSITION, 32 ) ); 
	$MD5_SYNTAX = md5( $SYNTAX );
	
	if( $RESULT_CACHE = @ $GLOBALS[ 'CACHE_' ][ $MD5_STRING ][ $MD5_SYNTAX ] )
	{
		$POSITION += $RESULT_CACHE[ 1 ];
		return( $RESULT_CACHE[ 0 ] );
	}

	$POSITION_OLD = $POSITION;
	$RESULT = match_element_no_cache( $STRING, $POSITION, $SYNTAX );
	@ $GLOBALS[ 'CACHE_' ][ $MD5_STRING ][ $MD5_SYNTAX ] = array( $RESULT, $POSITION - $POSITION_OLD );
	
	return( $RESULT );
}

function match_element_no_cache( $STRING, &$POSITION, $SYNTAX ) 
{
    // sintaxe de uma sintaxe: operando1 operador1 operando2 operador2 operando3 ...
    // * onde "operador" �: + ou |
    // * onde "operando" � composto de:
    //   * valor [* multiplicador]
    //   * "valor" pode ser:
    //     * uma faixa de literais
    //     * um sub-operando em par�nteses
    //     * um elemento
    //   * e multiplicador (opcional) pode ser um:
    //     * n�mero inteiro positivo
    //     * 2 n�meros inteiros e positivos separados por "..". Se este for o caso,
    //       o 2o n�mero pode ser a letra n (indica infinito)
    // ! OBS.: O multiplicador � sempre uma faixa. Se n�o for definido, fica implicita
    //  a faixa 1..1, se for definido apenas um �nico valor "a", ent�o a faixa �
    //  "a".."a".
	// PS: @ na frente de literal � usado para indicar insensibilidade � caixa

    // armazenamos a posi��o atual, para voltar em caso de erro
    $OLD_POSITION = $POSITION;
	
	global $LST_WORD;

    $RESULT = true;
    $OR = false;
    $SYNTAX_LENGTH = strlen ( $SYNTAX );
    $SYNTAX_POSITION = 0;
    $END = false;
    while ( ! $END )
	{
		$CASE_INSENSITIVE = false;
		
        // descobre se est� negada ou n�o
		
        // pula espa�os
        while ( ( $SYNTAX_POSITION <= $SYNTAX_LENGTH ) && ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == ' ' ) )
            $SYNTAX_POSITION ++;
		
        // se tem "!", express�o est� negada
		$NEGATION = false;
        if ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == '!' )
        {
            $NEGATION = true;
            $SYNTAX_POSITION ++;
        }
		
        // descobre valor e tipo
        switch ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) )
		{
			case '(':
				// sub-express�o
				
                // vai at� o ")" correspondente
                $SYNTAX_POSITION_END = $SYNTAX_POSITION + 1;
                $PARENTESIS_COUNT = 1;
                $LITERAL = false;
                while ( $PARENTESIS_COUNT <> 0 )
				{
					if ( substr ( $SYNTAX, $SYNTAX_POSITION_END, 1 ) == '"' )
						$LITERAL = ! $LITERAL;
					
                    if ( $LITERAL == false )
                    {
                        if ( substr ( $SYNTAX, $SYNTAX_POSITION_END, 1 ) == ')' ) 
							$PARENTESIS_COUNT --;
							
                        else if ( substr ( $SYNTAX, $SYNTAX_POSITION_END, 1 ) == '(' ) 
							$PARENTESIS_COUNT ++;
                    }
					
                    $SYNTAX_POSITION_END ++;
				}
				
                // pega sub-express�o
                $EXPRESSION = substr ( $SYNTAX, $SYNTAX_POSITION + 1, $SYNTAX_POSITION_END - $SYNTAX_POSITION - 2 );
				
                $TYPE = 'SUB_EXPRESSION';
            break;
			
			case '@':
				// literal insens�vel � caixa
				$CASE_INSENSITIVE = true;
				$SYNTAX_POSITION++;
            case '"':
				// literal
				
				// vai at� o pr�ximo "
				$SYNTAX_POSITION_END = $SYNTAX_POSITION + 1;
				while ( substr ( $SYNTAX, $SYNTAX_POSITION_END, 1 ) != '"' )
					$SYNTAX_POSITION_END ++;
				
				// pega faixa do literal
				$EXPRESSION = substr ( $SYNTAX, $SYNTAX_POSITION + 1, $SYNTAX_POSITION_END - $SYNTAX_POSITION - 1 );
				$SYNTAX_POSITION_END ++;
				
				// tem faixa ? ent�o pega ela !
				if ( ( $SYNTAX_POSITION_END + 2 <= $SYNTAX_LENGTH ) && ( substr ( $SYNTAX, $SYNTAX_POSITION_END, 2 ) == '..' ) )
				{
					$SYNTAX_POSITION = $SYNTAX_POSITION_END + 2;
					$SYNTAX_POSITION_END = $SYNTAX_POSITION + 1;
					
					while ( substr ( $SYNTAX, $SYNTAX_POSITION_END, 1 ) != '"' ) 
						$SYNTAX_POSITION_END ++;
					
					$EXPRESSION_END = substr ( $SYNTAX, $SYNTAX_POSITION + 1, $SYNTAX_POSITION_END - $SYNTAX_POSITION - 1 );
					$SYNTAX_POSITION_END ++;
				}
				
				// faixa n�o est� expl�cita...
				else 
					$EXPRESSION_END = $EXPRESSION;
				
				$TYPE = 'STRING';
			break;
            
            case '#':
                // caractere #hh
                $EXPRESSION = chr ( hexdec ( substr ( $SYNTAX, $SYNTAX_POSITION + 1, 2 ) ) + 0 );
                $SYNTAX_POSITION_END = $SYNTAX_POSITION + 3;
                $EXPRESSION_END = $EXPRESSION;
                $TYPE = 'STRING';
            break;

            default:
				// elemento
				
                // vai at� o delimitador (#32, "|", "+")
                $SYNTAX_POSITION_END = $SYNTAX_POSITION + 1;
                while ( ( $SYNTAX_POSITION_END <= $SYNTAX_LENGTH ) && ( strpos ( ' +|', substr ( $SYNTAX, $SYNTAX_POSITION_END, 1 ) ) === false ) )
					$SYNTAX_POSITION_END ++;
				
                // pega nome do elemento
                $EXPRESSION = substr ( $SYNTAX, $SYNTAX_POSITION, $SYNTAX_POSITION_END - $SYNTAX_POSITION );
				
                // busca sintaxe do elemento
                @ $EXPRESSION = $LST_WORD[ $EXPRESSION ];
                /*if ( $EXPRESSION === false ) 
					ShowMessage ('ERRO: '+lExp+' n�o definida.');*/
	
                $TYPE = 'ELEMENT';
            break;
		}
		
        // acerta lPosSym para pr�ximo caractere que n�o seja um espa�o
        $SYNTAX_POSITION = $SYNTAX_POSITION_END;
        while ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == ' ' )
			$SYNTAX_POSITION ++;
		
        // pega multiplicador (faixa)
		
        // est� expl�cito ? (tem o "*" ?)
        if ( ( $SYNTAX_POSITION <= $SYNTAX_LENGTH ) && ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == '*' ) )
		{
            // pula o "*"
            $SYNTAX_POSITION ++;
			
            // pula espa�os em branco
            while ( ( $SYNTAX_POSITION <= $SYNTAX_LENGTH ) && ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == ' ' ) )
				$SYNTAX_POSITION ++;
			
            // busca o multiplicador menor
			
            // pega enquanto for algarismos
            $SYNTAX_POSITION_END = $SYNTAX_POSITION;
            while ( ( $SYNTAX_POSITION_END <= $SYNTAX_LENGTH ) && ( strpos ( '0123456789', substr ( $SYNTAX, $SYNTAX_POSITION_END, 1 ) ) !== false ) )
				$SYNTAX_POSITION_END ++;
			
            // converte para n�mero
            $MULTIPLIER [ 'INITIAL' ] = substr ( $SYNTAX, $SYNTAX_POSITION, $SYNTAX_POSITION_END - $SYNTAX_POSITION ) + 0;
			
            // busca o multiplicador maior, se tiver
			
            // pula brancos
            $SYNTAX_POSITION = $SYNTAX_POSITION_END;
            while ( ( $SYNTAX_POSITION <= $SYNTAX_LENGTH ) && ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == ' ' ) )
				$SYNTAX_POSITION ++;
			
            // eh depois dos 2 pontos...
            if ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == '.' )
            {	
                $SYNTAX_POSITION += 2;
                $SYNTAX_POSITION_END = $SYNTAX_POSITION;
				
                // pega enquanto for algarismos ou "n"
                if ( substr ( $SYNTAX, $SYNTAX_POSITION_END, 1 ) == 'n' )
					$MULTIPLIER [ 'END' ] = -1;
				
                else
                {
					while ( ( $SYNTAX_POSITION_END <= $SYNTAX_LENGTH ) && ( strpos ( '0123456789', substr ( $SYNTAX, $SYNTAX_POSITION_END, 1 ) ) !== false ) )
						$SYNTAX_POSITION_END ++;
					
                    // converte para n�mero
					$MULTIPLIER [ 'END' ] = substr ( $SYNTAX, $SYNTAX_POSITION, $SYNTAX_POSITION_END - $SYNTAX_POSITION ) + 0;
				}
				
                $SYNTAX_POSITION = $SYNTAX_POSITION_END + 1;
            }
			
            // s� h� o menor
           else
               $MULTIPLIER [ 'END' ] = $MULTIPLIER [ 'INITIAL' ];
		}

		// est� impl�cito que � 1..1
		else
		{
			$MULTIPLIER = array (
				'INITIAL' => 1,
				'END' => 1
			);
		}
		
		// executa
		if ( $TYPE == 'STRING' )
		{
			// express�o deve aparecer no m�ximo "mulF" vezes. e deve estar entre exp e exp2
			$MULTIPLIER_COUNT = 0;
			
			if( $CASE_INSENSITIVE )
			{
				while ( ( $MULTIPLIER_COUNT != $MULTIPLIER [ 'END' ] ) &&
						( strtoupper( substr ( $STRING, $POSITION, strlen ( $EXPRESSION ) ) ) >= strtoupper( $EXPRESSION ) ) &&
						( strtoupper( substr ( $STRING, $POSITION, strlen ( $EXPRESSION_END ) ) ) <= strtoupper( $EXPRESSION_END ) ) &&
						( $POSITION < strlen ( $STRING ) ) )
				{
					$POSITION += strlen ( $EXPRESSION );
					$MULTIPLIER_COUNT ++;
				}
			}
			else
			{
				while ( ( $MULTIPLIER_COUNT != $MULTIPLIER [ 'END' ] ) &&
						( substr ( $STRING, $POSITION, strlen ( $EXPRESSION ) ) >= $EXPRESSION ) &&
						( substr ( $STRING, $POSITION, strlen ( $EXPRESSION_END ) ) <= $EXPRESSION_END ) &&
						( $POSITION < strlen ( $STRING ) ) )
				{
					$POSITION += strlen ( $EXPRESSION );
					$MULTIPLIER_COUNT ++;
				}	
			}
			
			// express�o tem aparecer um m�nimo de vezes
			$RESULT = ( $MULTIPLIER_COUNT >= $MULTIPLIER [ 'INITIAL' ] );
		}
		
		else
		{
			// express�o deve aparecer no m�ximo "mulF" vezes. e deve estar entre exp e exp2
			$MULTIPLIER_COUNT = 0;
			$LAST = $POSITION;
			while ( ( $MULTIPLIER_COUNT <> $MULTIPLIER [ 'END' ] ) && ( match_element ( $STRING, $POSITION, $EXPRESSION ) ) )
			{
				$MULTIPLIER_COUNT ++;
				$LAST = $POSITION;
			}
			
			// express�o tem aparecer um m�nimo de vezes
			$RESULT = ( $MULTIPLIER_COUNT >= $MULTIPLIER [ 'INITIAL' ] );
		}
		
		// vai at� o delimitador ("|", "+") e pula ele
		while ( ( $SYNTAX_POSITION <= $SYNTAX_LENGTH ) && ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == ' ' ) )
			$SYNTAX_POSITION ++;
		
        if ( $NEGATION )
            $RESULT = ! $RESULT;
            
		$OR = $OR || $RESULT;
		
		if ( ( $SYNTAX_POSITION <= $SYNTAX_LENGTH ) && ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == '|' ) )
		{
			// j� temos uma express�o como true, n�o precisamos analisar o resto dos "or". Pula at� o pr�ximo "+"
			if ( $OR == true )
			{
                $LITERAL = false;
				$LEVEL = 0;
				while ( ( $SYNTAX_POSITION <= $SYNTAX_LENGTH ) && ( ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) != '+' ) ) || ( $LEVEL != 0 ) || $LITERAL )
				{
                    if ( ! $LITERAL )
                    {
					    if ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == '(' ) 
						    $LEVEL ++;
						    
					    else if ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == ')' ) 
						    $LEVEL --;
                    }
                        
                    if ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == '"' ) 
                        $LITERAL = ! $LITERAL;
					
					$SYNTAX_POSITION ++;
				}
				
				$OR = false;
			}
		}
		
		else
		{
			if ( $OR == false )
			{
				$END = true;
				$RESULT = false;
			}
			
			$OR = false;
		}
		
		$SYNTAX_POSITION ++;
		
		while ( ( $SYNTAX_POSITION <= $SYNTAX_LENGTH ) && ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == ' ' ) )
			$SYNTAX_POSITION ++;
		
		if ( $SYNTAX_POSITION > $SYNTAX_LENGTH )
			$END = true;
	}
	
	// a express�o n�o est� de acordo com a sintaxe
	if ( ! $RESULT ) 
		$POSITION = $OLD_POSITION;

	return ( $RESULT );
}
	
// sem cache: emulado = ~26.000 mais lento que real
// com cache: emulado = ~ 1.000 mais lento que real
// aceit�vel ~ 1,5 mais lento que real
?>