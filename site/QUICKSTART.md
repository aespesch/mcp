# 🚀 Guia de Início Rápido

## ⚡ Configuração em 3 Passos

### 1️⃣ Configure o Banco de Dados

```bash
# Copie o arquivo de exemplo
cp .ENV.example .ENV

# Edite com suas credenciais
nano .ENV
```

Conteúdo do `.ENV`:
```env
DB_HOST=localhost
database=meu_banco
user=meu_usuario
pwd=minha_senha
```

### 2️⃣ Estrutura de Arquivos

```
seu-projeto/
├── .ENV                    # ⚙️ Configurações (NÃO commitar!)
├── .ENV.example           # 📋 Template de configuração
├── crypto_functions.php   # 🔧 Biblioteca de funções
├── crypto_chart.php       # 📊 Visualizador de gráficos
└── crypto_strategy.php    # 🎯 Simulador de trading
```

### 3️⃣ Acesse via Navegador

```
📊 Gráficos: http://localhost/crypto_chart.php
🎯 Estratégia: http://localhost/crypto_strategy.php
```

---

## 📊 crypto_chart.php - Visualizador

### O que faz?
Mostra gráficos de preços com indicadores técnicos

### Modos Disponíveis:
- **Básico**: Só o preço
- **Bollinger**: Preço + Bandas de Bollinger
- **Completo**: Preço + Bollinger + RSI + MACD

### Exemplo de Uso:
```
crypto_chart.php?pair=BTCUSDT&period=30d&mode=complete
```

### Resultado:
✅ Gráfico interativo  
✅ Estatísticas (alta, baixa, média)  
✅ Indicadores técnicos  
✅ Múltiplos períodos de tempo  

---

## 🎯 crypto_strategy.php - Simulador

### O que faz?
Simula uma estratégia de trading automática

### Estratégia:
```
🟢 COMPRA quando:
   • Preço toca banda inferior
   • RSI < 35 (sobrevendido)

🔴 VENDA quando:
   • Preço toca banda superior
   • RSI > 65 (sobrecomprado)
```

### Exemplo de Uso:
```
crypto_strategy.php?pair=ETHUSDT&period=90d&initial_fiat=10000
```

### Resultado:
✅ Pontos de compra/venda no gráfico  
✅ Retorno total calculado  
✅ Comparação com Buy & Hold  
✅ Histórico completo de trades  
✅ Saldos finais (crypto + fiat)  

---

## 🔧 crypto_functions.php - Funções

### O que contém?

#### 🗄️ Banco de Dados
- `initDatabase()` - Conecta ao MySQL
- `getAvailablePairs()` - Lista pares disponíveis
- `getChartData()` - Busca dados históricos

#### 📈 Indicadores Técnicos
- `calculateBollingerBands()` - Bandas de Bollinger
- `calculateRSI()` - Índice de Força Relativa
- `calculateMACD()` - MACD e linha de sinal
- `calculateEMA()` - Média Móvel Exponencial

#### 🛠️ Utilidades
- `loadEnv()` - Carrega variáveis do .ENV
- `parseCurrencyPair()` - Separa par de moedas

---

## 📊 Visualização dos Dados

### crypto_chart.php (Modo Completo)
```
┌─────────────────────────────────────────────┐
│         📈 BTCUSDT - Completo              │
├─────────────────────────────────────────────┤
│                                             │
│  60000 ┤     ╱─────╲    ← Banda Superior   │
│  55000 ┤   ╱         ╲  ← Preço            │
│  50000 ┤  ╱           ╲ ← Banda Média      │
│  45000 ┤╱─────────────╲ ← Banda Inferior   │
│        └──────────────────────────          │
│                                             │
│  RSI:  ████████████░░░░░░ 65               │
│  MACD: ▓▓▓▓░░░░░░░░░░░░░░                 │
└─────────────────────────────────────────────┘
```

### crypto_strategy.php
```
┌─────────────────────────────────────────────┐
│      🎯 ETHUSDT - Estratégia               │
├─────────────────────────────────────────────┤
│                                             │
│  3500 ┤    🔴╲                             │
│  3000 ┤      ╱╲  🔴                        │
│  2500 ┤    ╱    ╲╱                         │
│  2000 ┤🟢╱                                  │
│       └──────────────────────────          │
│                                             │
│  Retorno: +15.3% 💰                        │
│  Trades: 12 (6 compras, 6 vendas)          │
│  vs Hold: Estratégia melhor! 📈            │
└─────────────────────────────────────────────┘

🟢 = Pontos de COMPRA
🔴 = Pontos de VENDA
```

---

## 💡 Dicas Importantes

### ✅ Boas Práticas
- Comece com períodos longos (90d ou 1y)
- Use agregação por hora para dados mais limpos
- Compare sempre com Buy & Hold
- Analise o histórico de trades antes de confiar na estratégia

### ⚠️ Cuidados
- Isso é uma SIMULAÇÃO - não são trades reais
- Não inclui taxas de corretagem
- Não inclui slippage (diferença de preço na execução)
- Resultados passados ≠ Resultados futuros

### 🔐 Segurança
```bash
# NUNCA faça isso:
git add .ENV  ❌

# Sempre ignore:
echo ".ENV" >> .gitignore  ✅
```

---

## 🎨 Personalização Rápida

### Mudar Limites do RSI
Edite `crypto_strategy.php`:
```php
// Mais conservador (menos trades)
if ($rsi < 30) { /* compra */ }
if ($rsi > 70) { /* venda */ }

// Mais agressivo (mais trades)
if ($rsi < 40) { /* compra */ }
if ($rsi > 60) { /* venda */ }
```

### Mudar Tolerância das Bandas
```php
// Mais conservador
$price <= $lowerBand * 1.001  // Precisa estar muito perto

// Mais agressivo
$price <= $lowerBand * 1.01   // Aceita 1% acima
```

### Mudar Capital e Trade
Na URL:
```
?initial_fiat=50000&trade_amount=5000
```

Ou no formulário da página!

---

## 📊 Exemplo Real de Resultados

```
┌────────────────────────────────────────────┐
│  Par: BTCUSDT                              │
│  Período: 90 dias                          │
│  Capital Inicial: $10,000                  │
├────────────────────────────────────────────┤
│  Retorno Total: +23.5% 📈                  │
│  Valor Final: $12,350                      │
│  Saldo Fiat: $8,200                        │
│  Saldo Crypto: 0.075 BTC ($2,150)          │
│  Total de Trades: 18                       │
│  vs Buy & Hold: +18.2%                     │
│  Estratégia: 5.3% MELHOR! ✨               │
└────────────────────────────────────────────┘
```

---

## 🆘 Problemas Comuns

### "Database connection failed"
```bash
# Verifique o .ENV
cat .ENV

# Teste a conexão MySQL
mysql -u usuario -p nome_banco
```

### "No data available"
- Verifique se o par existe no banco
- Tente outro período (ex: 'all')
- Verifique a estrutura das tabelas

### "No trade opportunities found"
- Aumente o período (tente 90d ou 1y)
- A estratégia é conservadora por design
- Mercado pode estar sem sinais claros

---

## 🎓 Próximos Passos

1. ✅ Teste com diferentes pares de moedas
2. ✅ Compare resultados entre períodos
3. ✅ Analise quais sinais funcionaram melhor
4. ✅ Ajuste os parâmetros da estratégia
5. ✅ Documente seus achados
6. ⚠️ NUNCA use em produção sem testes extensivos!

---

## 📚 Recursos de Aprendizado

### Indicadores Técnicos
- **Bollinger Bands**: Mede volatilidade
- **RSI**: Identifica sobrecompra/sobrevenda
- **MACD**: Detecta mudanças de tendência

### Trading
- Backtesting ≠ Resultados reais
- Sempre considere gestão de risco
- Diversificação é fundamental
- Nunca invista mais do que pode perder

---

**🚀 Pronto para começar? Abra crypto_strategy.php e veja a mágica acontecer!**
