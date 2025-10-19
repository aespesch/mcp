# 📊 Sistema de Análise Técnica e Trading de Criptomoedas

Sistema completo para visualização de gráficos de criptomoedas com indicadores técnicos e simulação de estratégias de trading.

## 📁 Arquivos Criados

### 1. **crypto_functions.php**
Biblioteca de funções reutilizáveis contendo:
- Conexão com banco de dados MySQL
- Carregamento de variáveis de ambiente (.ENV)
- Cálculo de indicadores técnicos:
  - Bandas de Bollinger
  - RSI (Relative Strength Index)
  - MACD (Moving Average Convergence Divergence)
  - EMA (Exponential Moving Average)
- Funções de consulta ao banco de dados
- Parser de pares de moedas

### 2. **crypto_chart.php**
Visualizador de gráficos com três modos:
- **Modo Básico**: Apenas preço
- **Modo Bollinger**: Preço + Bandas de Bollinger
- **Modo Completo**: Preço + Bollinger + RSI + MACD

**Recursos:**
- Seleção de pares de criptomoedas
- Filtros de período (24h, 7d, 30d, 90d, 1y, tudo)
- Agregação de dados (raw, hora, dia)
- Estatísticas em tempo real
- Gráficos interativos com Chart.js

### 3. **crypto_strategy.php** ⭐
Simulador de estratégia de trading com visualização de sinais de compra/venda.

**Estratégia Implementada:**

🟢 **Sinais de COMPRA:**
- Preço toca a banda inferior de Bollinger (dentro de 0.5%)
- RSI < 35 (ativo sobrevendido)
- Não estar em posição

🔴 **Sinais de VENDA:**
- Preço toca a banda superior de Bollinger (dentro de 0.5%)
- RSI > 65 (ativo sobrecomprado)
- Estar em posição long

**Recursos:**
- Simulação completa de trades
- Visualização gráfica dos pontos de compra/venda
- Cálculo de retorno total vs Buy & Hold
- Gestão de saldo (crypto + fiat)
- Tabela detalhada de todas as operações
- Métricas de performance em tempo real

## 🚀 Como Usar

### Pré-requisitos

1. **Servidor PHP** (versão 7.4 ou superior)
2. **Banco de dados MySQL** com as tabelas:
   - `candle_time`
   - `candle_day`
   - `symbol_pair`
   - `symbol`
3. **Arquivo .ENV** na mesma pasta com as credenciais do banco:

```env
DB_HOST=localhost
database=nome_do_banco
user=usuario
pwd=senha
```

### Instalação

1. Coloque os três arquivos PHP no mesmo diretório
2. Configure o arquivo `.ENV` com suas credenciais
3. Acesse via navegador

### Uso dos Arquivos

#### Visualizar Gráficos (crypto_chart.php)
```
http://seu-servidor/crypto_chart.php
```
- Selecione um par de moedas
- Escolha o período e agregação
- Selecione o modo de visualização

#### Simular Estratégia (crypto_strategy.php)
```
http://seu-servidor/crypto_strategy.php
```
- Selecione um par de moedas
- Configure capital inicial (padrão: 10.000)
- Configure valor por trade (padrão: 1.000)
- Escolha período e agregação
- Visualize os resultados da simulação

## 📊 Parâmetros da URL

### crypto_chart.php
- `pair`: Par de moedas (ex: BTCUSDT)
- `period`: 24h, 7d, 30d, 90d, 1y, all
- `aggregation`: raw, hour, day
- `mode`: basic, bollinger, complete

Exemplo:
```
crypto_chart.php?pair=BTCUSDT&period=30d&aggregation=hour&mode=complete
```

### crypto_strategy.php
- `pair`: Par de moedas (ex: BTCUSDT)
- `period`: 7d, 30d, 90d, 1y, all
- `aggregation`: hour, day
- `initial_fiat`: Capital inicial (padrão: 10000)
- `trade_amount`: Valor por trade (padrão: 1000)

Exemplo:
```
crypto_strategy.php?pair=ETHUSDT&period=90d&aggregation=day&initial_fiat=50000&trade_amount=5000
```

## 🎨 Características Visuais

### crypto_chart.php
- Design moderno com gradiente roxo/azul
- Cards de estatísticas coloridos
- Gráficos responsivos e interativos
- Tooltips informativos
- Indicadores técnicos com cores distintas

### crypto_strategy.php
- Design escuro com gradiente azul/roxo
- Cards de performance com cores (verde=positivo, vermelho=negativo)
- Pontos de compra/venda destacados no gráfico:
  - 🟢 Verde: Compras
  - 🔴 Vermelho: Vendas
- Tabela detalhada de todas as operações
- Comparação automática com estratégia Buy & Hold

## 📈 Indicadores Técnicos

### Bandas de Bollinger
- **Período**: 20
- **Desvio Padrão**: 2.0
- **Uso**: Identificar sobrecompra/sobrevenda

### RSI (Relative Strength Index)
- **Período**: 14
- **Zona de Sobrevenda**: < 30
- **Zona de Sobrecompra**: > 70
- **Uso**: Confirmar sinais de entrada/saída

### MACD
- **EMA Rápida**: 12
- **EMA Lenta**: 26
- **Linha de Sinal**: 9
- **Uso**: Identificar mudanças de tendência

## ⚙️ Estrutura do Banco de Dados

O sistema espera a seguinte estrutura de tabelas:

```sql
-- Tabela de símbolos (moedas)
symbol (smbl_id, smbl_code)

-- Tabela de pares
symbol_pair (smpr_id, smpr_base_symbol_id, smpr_quote_symbol_id)

-- Tabela de dias
candle_day (cndl_id, cndl_date, cndl_symbol_pair_id)

-- Tabela de dados por minuto
candle_time (
    cntm_id,
    cntm_candle_day_id,
    cntm_minutes,
    cntm_open_price,
    cntm_close_price,
    cntm_high_price,
    cntm_low_price
)
```

## 🎯 Resultados da Estratégia

O simulador exibe:

1. **Retorno Total**: Ganho/perda percentual e em valor
2. **Valor Final**: Capital total após todas as operações
3. **Saldo Fiat**: Dinheiro em moeda fiduciária
4. **Saldo Crypto**: Quantidade de criptomoeda mantida
5. **Total de Trades**: Número de operações realizadas
6. **vs Buy & Hold**: Comparação com estratégia passiva

## 🛡️ Considerações de Segurança

1. **NÃO** exponha o arquivo `.ENV` na web
2. Use prepared statements (já implementado)
3. Valide todas as entradas do usuário
4. Configure permissões adequadas nos arquivos PHP
5. Use HTTPS em produção

## 📝 Notas Importantes

- Esta é uma **SIMULAÇÃO** - não executa trades reais
- Os resultados passados não garantem resultados futuros
- Sempre considere taxas de corretagem e slippage em trading real
- A estratégia é educacional e deve ser testada extensivamente antes de uso real
- Backtesting não substitui experiência e análise de mercado

## 🔧 Personalização

Para modificar a estratégia, edite o arquivo `crypto_strategy.php` na seção de detecção de sinais:

```php
// Ajuste os limites do RSI
if ($rsi < 35) { // Torne mais conservador: $rsi < 30
    // Sinal de compra
}

if ($rsi > 65) { // Torne mais conservador: $rsi > 70
    // Sinal de venda
}
```

## 📞 Suporte

Para questões sobre:
- **Banco de dados**: Verifique a estrutura das tabelas
- **Indicadores**: Consulte a documentação dos cálculos em `crypto_functions.php`
- **Estratégia**: Leia os comentários em `crypto_strategy.php`

## 📄 Licença

Código fornecido para fins educacionais. Use por sua conta e risco.

---

**Desenvolvido com ❤️ para análise técnica de criptomoedas**
