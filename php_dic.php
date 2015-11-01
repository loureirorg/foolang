<?php
fl_word( 'RESERVED', '@"echo" | @"while" | @"if" | @"elseif" | @"else" | @"break" | @"continue" | @"exit" | @"switch" | @"default" | @"case" | @"return" | @"do" | "function" | @"array" | @"foreach" | @"for"' );
fl_word( 'CHAR', '"'. chr ( 0x00 ) .'".."'. chr ( 0xff ) .'"' );
fl_word( 'ESP_BRK', '"'. chr ( 0x00 ) .'".."'. chr ( 0x20 ) .'" | COMMENT' ); // espaço em branco ou quebra de linha
fl_word( 'ESP_BRKS', 'ESP_BRK * 0..n' ); // vários
fl_word( 'ALFA', '"A".."Z" | "a".."z" | "_"' );
fl_word( 'DIGIT', '"0".."9"' );
fl_word( 'ZERO', '"0" + !DIGIT' ); // número zero
fl_word( 'OCTAL', '"0" + ( "1".."7" + "0".."7" * 0..n )' ); // número octal: 0nro
fl_word( 'BINARY', '"00x" + "0".."1" * 1..n' ); // número binário: 00xnro
fl_word( 'HEXA', '"0x" + ( DIGIT | "A".."F" | "a".."f" ) * 1..n' ); // número hexadecimal: 0xnro
fl_word( 'DECIMAL', 'ZERO | ( "1".."9" + DIGIT * 0..n ) + !( ALFA | "." )' ); // número decimal inteiro
fl_word( 'REAL', '( ZERO | ( "1".."9" + DIGIT * 0..n ) ) + "." + DIGIT * 1..n' ); // número decimal real
fl_word( 'SCIENTIFIC', 'REAL + "E" + ("+" | "-") + DECIMAL' );
fl_word( 'NUMBER', '( ( "!" + ESP_BRKS ) * 0..1 + ( "+" | "-" ) + ESP_BRKS ) * 0..1 + ( SCIENTIFIC | BINARY | HEXA | OCTAL | DECIMAL | REAL ) + !ALFA' ); // número em qualquer base
fl_word( 'STRING', 'STRING_SINGLE | STRING_DOUBLE' ); // literal
fl_word( 'STRING_SINGLE', '"'. chr ( 39 ) .'" + ( "'. chr ( 40 ) .'".."'. chr ( 255 ) .'" | "\\'. chr ( 39 ) .'" | "'. chr ( 0 ).'".."'. chr ( 38 ).'" ) * 0..n + "'. chr ( 39 ).'"' );
fl_word( 'STRING_DOUBLE', '#22 + ( "'. chr ( 35 ) .'".."'. chr ( 255 ) .'" | ( "\\" + #22 ) | "'. chr ( 0 ).'".."'. chr ( 33 ).'" ) * 0..n + #22' );
fl_word( 'VAR_NAME', 'RESERVED * 0 + ALFA + ( ALFA | DIGIT ) * 0..n' );
fl_word( 'PRG', 'ESP_BRKS + ( ( FNC | CODE ) + ESP_BRKS ) * 0..n' );
fl_word( 'FNC', '@"function" + ESP_BRKS + VAR_NAME + ESP_BRKS + FNC_PARAMS + ESP_BRKS + CODE' );
fl_word( 'FNC_PARAMS', 'FNC_PARAMS_VAZIO | FNC_PARAMS_CHEIO' );
fl_word( 'FNC_PARAMS_CHEIO', '"(" + ESP_BRKS + ( VAR + ESP_BRKS + "," + ESP_BRKS ) * 0..n + VAR * 0..1 + ESP_BRKS + ")"' );
fl_word( 'FNC_PARAMS_VAZIO', '"(" + ESP_BRKS + ")"' );
fl_word( 'CODE', 'BLOCK | COMMAND' );
fl_word( 'BLOCK', '"{" + ESP_BRKS + ( COMMAND + ESP_BRKS ) * 0..n + "}"' );
fl_word( 'COMMAND', 'ECHO | IF | FOREACH | FOR | WHILE | DOWHILE | SWITCH | RETURN | ( EXPRESSION + ESP_BRKS + ";" ) | GLOBAL | BREAK | CONTINUE' );
fl_word( 'BREAK', '@"break" + ESP_BRKS + ( "(" + ESP_BRKS + EXPRESSION + ESP_BRKS + ")" + ESP_BRKS ) * 0..1 + ";"' );
fl_word( 'CONTINUE', '@"continue" + ESP_BRKS + ( "(" + ESP_BRKS + EXPRESSION + ESP_BRKS + ")" + ESP_BRKS ) * 0..1 + ";"' );
fl_word( 'OPR_ASSGN', '"+=" | "-=" | "*=" | "/=" | "%=" | "&=" | "|=" | "^=" | ">>=" | "<<=" | ".="' ); // operador de atribuição
fl_word( 'ASSIGN_INC', 'VAR + ESP_BRKS + "++"' );
fl_word( 'ASSIGN_DEC', 'VAR + ESP_BRKS + "--"' );
fl_word( 'ASSIGN_CTR', 'VAR + ESP_BRKS + OPR_ASSGN + ESP_BRKS + MATH' );
fl_word( 'ASSIGN_NRM', 'VAR + ESP_BRKS + "=" + ESP_BRKS + MATH' );
fl_word( 'ASSIGN', 'REFERENCE_ASSIGN | ASSIGN_INC | ASSIGN_CTR | ASSIGN_NRM' );
fl_word( 'SYMB_OPR', '"=" | "+" | "-" | "*" | "/" | "%" | ">" | "<" | "&" | "|" | "^"' );
fl_word( 'OPR_MATH', '( "===" | "!==" | ">>" | "<<" | ">=" | "<=" | "==" | "!=" | "&&" | "||" | "^^" | "+" | "-" | "*" | "/" | "%" | ">" | "<" | "&" | "|" | "^" | "." ) + !SYMB_OPR' );
fl_word( 'VAR', 'VAR_SINGLE | VAR_COMPLEX' );
fl_word( 'VAR_SINGLE', '"$" + VAR_NAME + ( ESP_BRKS + "[" + ESP_BRKS + EXPRESSION + ESP_BRKS + "]" ) * 0..n' );
fl_word( 'VAR_COMPLEX', '"$" + VAR_SINGLE' ); 
fl_word( 'REFERENCE_ASSIGN', '"=" + ESP_BRKS + "&"' ); 
fl_word( 'GLOBAL', '@"global" + ESP_BRKS + "$" + VAR_NAME + ESP_BRKS + ";"' );
fl_word( 'CONSTANT', 'RESERVED * 0 + VAR_NAME' ); //!RESERVED + VAR_NAME
fl_word( 'NEGATIVE', '( "!" | "~" )' );
fl_word( 'OPERANDO', '( NEGATIVE + ESP_BRKS ) * 0..1 + ( TERNARY | ( "(" + ESP_BRKS + MATH + ESP_BRKS + ")" ) | VAR | NUMBER | STRING | ARRAY | CALL | ( "(" + ESP_BRKS + ASSIGN + ESP_BRKS + ")" ) | @"true" | @"false" | CONSTANT )' );
fl_word( 'MATH', 'OPERANDO + ESP_BRKS + ( OPR_MATH + ESP_BRKS + OPERANDO + ESP_BRKS ) * 0..n');
fl_word( 'EXPRESSION', 'ASSIGN | MATH' );
fl_word( 'WHILE', '@"while" + ESP_BRKS + EXPRESSION + ESP_BRKS + CODE' );
fl_word( 'DOWHILE', '@"do" + ESP_BRKS + BLOCK + ESP_BRKS + @"while" + ESP_BRKS + EXPRESSION + ESP_BRKS + ";"' );
fl_word( 'FOR', '@"for" + ESP_BRKS + "(" + ESP_BRKS + EXPRESSION * 0..1 + ESP_BRKS + ";" + ESP_BRKS + EXPRESSION + ESP_BRKS + ";" + ESP_BRKS + EXPRESSION + ESP_BRKS + ")" + ESP_BRKS + CODE' );
fl_word( 'ELSEIF', '@"elseif" + ESP_BRKS + ( EXPRESSION ) + ESP_BRKS + ( CODE )' );
fl_word( 'ELSE', '@"else" + ESP_BRKS + ( CODE )' );
fl_word( 'IF', '@"if" + ESP_BRKS + ( EXPRESSION ) + ESP_BRKS + ( CODE ) + ESP_BRKS + ( ELSEIF * 0..1 ) + ( ELSE * 0..1 )' );
fl_word( 'RETURN', '@"return" + ESP_BRKS + CALL_PARAM_ONE + ";"' );
fl_word( 'CALL_PARAM_ONE', '"(" + ESP_BRKS + EXPRESSION * 0..1 + ESP_BRKS + ")"' );
fl_word( 'CALL', '!RESERVED + VAR_NAME + ESP_BRKS + CALL_PARAMS' );
fl_word( 'CALL_PARAMS', 'CALL_PARAMS_VAZIO | CALL_PARAMS_CHEIO' );
fl_word( 'PARAM_SEQUENCE', 'EXPRESSION + ESP_BRKS + "," + ESP_BRKS' );
fl_word( 'CALL_PARAMS_CHEIO', '"(" + ESP_BRKS + PARAM_SEQUENCE * 0..n + EXPRESSION + ESP_BRKS + ")"' );
fl_word( 'CALL_PARAMS_VAZIO', '"(" + ESP_BRKS + ")"' );
fl_word( 'SWITCH', '@"switch" + ESP_BRKS + EXPRESSION + ESP_BRKS + "{" + ESP_BRKS + ( @"case" + ESP_BRKS + EXPRESSION + ESP_BRKS + ":" + ESP_BRKS + ( CODE + ESP_BRKS ) * 0..n ) * 0..n + ( @"default" + ESP_BRKS + ":" + ESP_BRKS + ( CODE + ESP_BRKS ) * 0..n ) * 0..1 + ESP_BRKS + "}"' );
fl_word( 'CHAR_MINUS_BRK', '"'. chr ( 0x00 ) .'".."'. chr ( 0x09 ) .'" | "'. chr ( 0x0b ) .'".."'. chr ( 0x0c ) .'" | "'. chr ( 0x0e ) .'".."'. chr ( 0xff ) .'"' );
fl_word( 'COMMENT', 'COMMENT_ONE_LINE | COMMENT_MULTIPLE_LINE' ); 
fl_word( 'COMMENT_ONE_LINE', ' ( "//" | #23 ) + CHAR_MINUS_BRK * 0..n + #0d' ); 
fl_word( 'COMMENT_MULTIPLE_LINE_END', 'CHAR + !"*/"' ); 
fl_word( 'COMMENT_MULTIPLE_LINE', '"/*" + COMMENT_MULTIPLE_LINE_END * 0..n + CHAR + "*/"' ); 
fl_word( 'ARRAY_ITEM', 'ARRAY_ITEM_WITH_KEY | EXPRESSION' );
fl_word( 'ARRAY_ITEM_WITH_KEY', 'EXPRESSION + ESP_BRKS + "=>" + ESP_BRKS + EXPRESSION' );
fl_word( 'ARRAY_PARAMS_CHEIO', '"(" + ESP_BRKS + ( ARRAY_ITEM + ESP_BRKS + "," + ESP_BRKS ) * 0..n + ARRAY_ITEM * 0..1 + ESP_BRKS + ")"' );
fl_word( 'ARRAY', '@"array" + ESP_BRKS + ( CALL_PARAMS_VAZIO | ARRAY_PARAMS_CHEIO )' );
fl_word( 'FOREACH', 'FOREACH_SINGLE | FOREACH_COMPLEX' );
fl_word( 'FOREACH_SINGLE', '@"foreach" + ESP_BRKS + "(" + ESP_BRKS + EXPRESSION + ESP_BRKS + @"as" + ESP_BRKS + VAR + ESP_BRKS + ")" + ESP_BRKS + CODE' );
fl_word( 'FOREACH_COMPLEX', '@"foreach" + ESP_BRKS + "(" + ESP_BRKS + EXPRESSION + ESP_BRKS + @"as" + ESP_BRKS + VAR + ESP_BRKS + "=>" + ESP_BRKS + VAR + ESP_BRKS + ")" + ESP_BRKS + CODE' );
fl_word( 'TERNARY', '"(" + ESP_BRKS + EXPRESSION + ESP_BRKS + ")" + ESP_BRKS + "?" + ESP_BRKS + EXPRESSION + ESP_BRKS + ":" + ESP_BRKS + EXPRESSION' );
fl_word( 'ECHO', '@"echo" + ESP_BRKS + EXPRESSION' );
?>