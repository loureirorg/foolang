<?php
$GLOBALS[ 'LST_WORD' ] = array ();

// FooLang Match: função que quebra cada sentença em um registro de vetor.
// só retorna grupos entre (). Funcionamento igual a preg_match
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
// valor    = array ( v ou f, diff posição )

function match_element( $STRING, &$POSITION, $SYNTAX ) 
{
	// dica para otimização: usar padrão "diminuição de gerenciamento de dados" = não é necessário fazer um md5 de position até o final da linha
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
    // * onde "operador" é: + ou |
    // * onde "operando" é composto de:
    //   * valor [* multiplicador]
    //   * "valor" pode ser:
    //     * uma faixa de literais
    //     * um sub-operando em parênteses
    //     * um elemento
    //   * e multiplicador (opcional) pode ser um:
    //     * número inteiro positivo
    //     * 2 números inteiros e positivos separados por "..". Se este for o caso,
    //       o 2o número pode ser a letra n (indica infinito)
    // ! OBS.: O multiplicador é sempre uma faixa. Se não for definido, fica implicita
    //  a faixa 1..1, se for definido apenas um único valor "a", então a faixa é
    //  "a".."a".
	// PS: @ na frente de literal é usado para indicar insensibilidade à caixa

    // armazenamos a posição atual, para voltar em caso de erro
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
		
        // descobre se está negada ou não
		
        // pula espaços
        while ( ( $SYNTAX_POSITION <= $SYNTAX_LENGTH ) && ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == ' ' ) )
            $SYNTAX_POSITION ++;
		
        // se tem "!", expressão está negada
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
				// sub-expressão
				
                // vai até o ")" correspondente
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
				
                // pega sub-expressão
                $EXPRESSION = substr ( $SYNTAX, $SYNTAX_POSITION + 1, $SYNTAX_POSITION_END - $SYNTAX_POSITION - 2 );
				
                $TYPE = 'SUB_EXPRESSION';
            break;
			
			case '@':
				// literal insensível à caixa
				$CASE_INSENSITIVE = true;
				$SYNTAX_POSITION++;
            case '"':
				// literal
				
				// vai até o próximo "
				$SYNTAX_POSITION_END = $SYNTAX_POSITION + 1;
				while ( substr ( $SYNTAX, $SYNTAX_POSITION_END, 1 ) != '"' )
					$SYNTAX_POSITION_END ++;
				
				// pega faixa do literal
				$EXPRESSION = substr ( $SYNTAX, $SYNTAX_POSITION + 1, $SYNTAX_POSITION_END - $SYNTAX_POSITION - 1 );
				$SYNTAX_POSITION_END ++;
				
				// tem faixa ? então pega ela !
				if ( ( $SYNTAX_POSITION_END + 2 <= $SYNTAX_LENGTH ) && ( substr ( $SYNTAX, $SYNTAX_POSITION_END, 2 ) == '..' ) )
				{
					$SYNTAX_POSITION = $SYNTAX_POSITION_END + 2;
					$SYNTAX_POSITION_END = $SYNTAX_POSITION + 1;
					
					while ( substr ( $SYNTAX, $SYNTAX_POSITION_END, 1 ) != '"' ) 
						$SYNTAX_POSITION_END ++;
					
					$EXPRESSION_END = substr ( $SYNTAX, $SYNTAX_POSITION + 1, $SYNTAX_POSITION_END - $SYNTAX_POSITION - 1 );
					$SYNTAX_POSITION_END ++;
				}
				
				// faixa não está explícita...
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
				
                // vai até o delimitador (#32, "|", "+")
                $SYNTAX_POSITION_END = $SYNTAX_POSITION + 1;
                while ( ( $SYNTAX_POSITION_END <= $SYNTAX_LENGTH ) && ( strpos ( ' +|', substr ( $SYNTAX, $SYNTAX_POSITION_END, 1 ) ) === false ) )
					$SYNTAX_POSITION_END ++;
				
                // pega nome do elemento
                $EXPRESSION = substr ( $SYNTAX, $SYNTAX_POSITION, $SYNTAX_POSITION_END - $SYNTAX_POSITION );
				
                // busca sintaxe do elemento
                @ $EXPRESSION = $LST_WORD[ $EXPRESSION ];
                /*if ( $EXPRESSION === false ) 
					ShowMessage ('ERRO: '+lExp+' não definida.');*/
	
                $TYPE = 'ELEMENT';
            break;
		}
		
        // acerta lPosSym para próximo caractere que não seja um espaço
        $SYNTAX_POSITION = $SYNTAX_POSITION_END;
        while ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == ' ' )
			$SYNTAX_POSITION ++;
		
        // pega multiplicador (faixa)
		
        // está explícito ? (tem o "*" ?)
        if ( ( $SYNTAX_POSITION <= $SYNTAX_LENGTH ) && ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == '*' ) )
		{
            // pula o "*"
            $SYNTAX_POSITION ++;
			
            // pula espaços em branco
            while ( ( $SYNTAX_POSITION <= $SYNTAX_LENGTH ) && ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == ' ' ) )
				$SYNTAX_POSITION ++;
			
            // busca o multiplicador menor
			
            // pega enquanto for algarismos
            $SYNTAX_POSITION_END = $SYNTAX_POSITION;
            while ( ( $SYNTAX_POSITION_END <= $SYNTAX_LENGTH ) && ( strpos ( '0123456789', substr ( $SYNTAX, $SYNTAX_POSITION_END, 1 ) ) !== false ) )
				$SYNTAX_POSITION_END ++;
			
            // converte para número
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
					
                    // converte para número
					$MULTIPLIER [ 'END' ] = substr ( $SYNTAX, $SYNTAX_POSITION, $SYNTAX_POSITION_END - $SYNTAX_POSITION ) + 0;
				}
				
                $SYNTAX_POSITION = $SYNTAX_POSITION_END + 1;
            }
			
            // só há o menor
           else
               $MULTIPLIER [ 'END' ] = $MULTIPLIER [ 'INITIAL' ];
		}

		// está implícito que é 1..1
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
			// expressão deve aparecer no máximo "mulF" vezes. e deve estar entre exp e exp2
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
			
			// expressão tem aparecer um mínimo de vezes
			$RESULT = ( $MULTIPLIER_COUNT >= $MULTIPLIER [ 'INITIAL' ] );
		}
		
		else
		{
			// expressão deve aparecer no máximo "mulF" vezes. e deve estar entre exp e exp2
			$MULTIPLIER_COUNT = 0;
			$LAST = $POSITION;
			while ( ( $MULTIPLIER_COUNT <> $MULTIPLIER [ 'END' ] ) && ( match_element ( $STRING, $POSITION, $EXPRESSION ) ) )
			{
				$MULTIPLIER_COUNT ++;
				$LAST = $POSITION;
			}
			
			// expressão tem aparecer um mínimo de vezes
			$RESULT = ( $MULTIPLIER_COUNT >= $MULTIPLIER [ 'INITIAL' ] );
		}
		
		// vai até o delimitador ("|", "+") e pula ele
		while ( ( $SYNTAX_POSITION <= $SYNTAX_LENGTH ) && ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == ' ' ) )
			$SYNTAX_POSITION ++;
		
        if ( $NEGATION )
            $RESULT = ! $RESULT;
            
		$OR = $OR || $RESULT;
		
		if ( ( $SYNTAX_POSITION <= $SYNTAX_LENGTH ) && ( substr ( $SYNTAX, $SYNTAX_POSITION, 1 ) == '|' ) )
		{
			// já temos uma expressão como true, não precisamos analisar o resto dos "or". Pula até o próximo "+"
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
	
	// a expressão não está de acordo com a sintaxe
	if ( ! $RESULT ) 
		$POSITION = $OLD_POSITION;

	return ( $RESULT );
}
	
// sem cache: emulado = ~26.000 mais lento que real
// com cache: emulado = ~ 1.000 mais lento que real
// aceitável ~ 1,5 mais lento que real
?>